<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Models\AffiliationPayment;
use App\Models\AffiliationPlan;
use App\Models\AuditLog;
use App\Models\DigitalCredential;
use App\Models\Person;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AffiliateAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_secretary_updates_personal_data_and_email_stays_synchronized(): void
    {
        [$affiliateUser, $affiliate] = $this->affiliate();
        $secretary = $this->internalUser('secretaria');

        $this->actingAs($secretary)->patch(route('admin.affiliates.personal.update', $affiliate), [
            'phone' => '77711122',
            'email' => 'nuevo-afiliado@test.local',
            'address' => 'CALLE CENTRAL',
            'birth_date' => '1990-05-10',
            'marital_status' => 'SOLTERO',
        ])->assertRedirect();

        $affiliate->refresh();
        $this->assertSame('nuevo-afiliado@test.local', $affiliate->email);
        $this->assertSame('nuevo-afiliado@test.local', $affiliate->user->email);
        $this->assertSame('nuevo-afiliado@test.local', $affiliate->person->email);
        $this->assertDatabaseHas('audit_logs', ['action' => 'affiliate_personal_data_updated', 'auditable_id' => $affiliate->id]);
        $this->assertNotSame($affiliateUser->email, $affiliate->email);
    }

    public function test_duplicate_email_is_rejected(): void
    {
        [, $affiliate] = $this->affiliate('uno@test.local');
        $other = User::factory()->create(['email' => 'duplicado@test.local']);

        $this->actingAs($this->internalUser('secretaria'))->patch(route('admin.affiliates.personal.update', $affiliate), [
            'email' => $other->email,
        ])->assertSessionHasErrors('email');
    }

    public function test_secretary_cannot_soft_delete_affiliate(): void
    {
        [, $affiliate] = $this->affiliate();

        $this->actingAs($this->internalUser('secretaria'))->delete(route('affiliates.destroy', $affiliate), [
            'confirmation' => 'ELIMINAR',
            'deletion_reason' => 'Intento no autorizado.',
        ])->assertForbidden();
    }

    public function test_manager_changes_sector_preserving_number_token_and_credential_identity(): void
    {
        [, $affiliate] = $this->affiliate();
        $credential = $this->credential($affiliate);
        $newSector = Sector::create(['name' => 'SALUD', 'code' => 'SAL', 'regional' => 'LA PAZ', 'institution' => 'HOSPITAL', 'is_active' => true]);
        $oldNumber = $affiliate->registration_number;
        $oldToken = $affiliate->verification_token;

        $this->actingAs($this->internalUser('gerente'))->patch(route('admin.affiliates.sector.update', $affiliate), [
            'sector_id' => $newSector->id,
        ])->assertRedirect();

        $affiliate->refresh();
        $credential->refresh();
        $this->assertSame($newSector->id, $affiliate->sector_id);
        $this->assertSame($oldNumber, $affiliate->registration_number);
        $this->assertSame($oldToken, $affiliate->verification_token);
        $this->assertSame($oldToken, $affiliate->fresh()->verification_token);
        $this->assertNull($credential->pdf_path);
        $this->assertDatabaseHas('audit_logs', ['action' => 'affiliate_sector_changed', 'auditable_id' => $affiliate->id]);

        $this->actingAs($affiliate->user)->getJson('/api/mobile/v1/me')
            ->assertOk()
            ->assertJsonPath('data.profile.affiliate.sector.name', 'SALUD');
    }

    public function test_manager_changes_plan_recalculating_balance_without_deleting_payments(): void
    {
        [, $affiliate] = $this->affiliate();
        AffiliationPayment::create(['affiliate_id' => $affiliate->id, 'amount' => 100, 'paid_amount' => 100, 'status' => 'confirmado']);
        $newPlan = AffiliationPlan::create(['name' => 'PLAN MAYOR', 'affiliation_fee' => 200, 'credential_fee' => 50, 'is_active' => true]);

        $this->actingAs($this->internalUser('gerente'))->patch(route('admin.affiliates.plan.update', $affiliate), [
            'affiliation_plan_id' => $newPlan->id,
        ])->assertRedirect();

        $this->assertSame($newPlan->id, $affiliate->fresh()->affiliation_plan_id);
        $this->assertSame(1, $affiliate->payments()->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'affiliate_plan_changed', 'auditable_id' => $affiliate->id]);
    }

    public function test_cashier_cannot_update_institutional_data(): void
    {
        [, $affiliate] = $this->affiliate();

        $this->actingAs($this->internalUser('cajero'))->patch(route('admin.affiliates.institutional.update', $affiliate), [
            'institution' => 'CAJA',
        ])->assertForbidden();
    }

    public function test_suspension_preserves_payments_and_marks_credential_not_active(): void
    {
        [, $affiliate] = $this->affiliate();
        $credential = $this->credential($affiliate);
        AffiliationPayment::create(['affiliate_id' => $affiliate->id, 'amount' => 100, 'status' => 'confirmado']);

        $this->actingAs($this->internalUser('gerente'))->patch(route('admin.affiliates.status.update', $affiliate), [
            'action' => 'suspend',
            'reason' => 'Revision administrativa.',
        ])->assertRedirect();

        $this->assertSame('suspendido', $affiliate->fresh()->status);
        $this->assertSame(1, $affiliate->payments()->count());
        $this->assertSame('suspendida', $credential->fresh()->status);
        $this->actingAs($affiliate->user)->getJson('/api/mobile/v1/me')->assertForbidden();
    }

    public function test_reactivation_reuses_existing_credential(): void
    {
        [, $affiliate] = $this->affiliate();
        $credential = $this->credential($affiliate, ['status' => 'suspendida', 'suspended_at' => now()]);
        $affiliate->update(['status' => 'suspendido']);

        $this->actingAs($this->internalUser('gerente'))->patch(route('admin.affiliates.status.update', $affiliate), [
            'action' => 'reactivate',
        ])->assertRedirect();

        $this->assertSame('activo', $affiliate->fresh()->status);
        $this->assertSame($credential->id, $affiliate->fresh('credential')->credential->id);
        $this->assertSame('vigente', $credential->fresh()->status);
    }

    public function test_credential_regeneration_does_not_change_token(): void
    {
        Storage::fake('public');
        [, $affiliate] = $this->affiliate();
        Storage::disk('public')->put('credentials/qr/'.$affiliate->registration_number.'.png', 'qr');
        $oldToken = $affiliate->verification_token;

        $this->actingAs($this->internalUser('gerente'))->post(route('admin.affiliates.credential.regenerate', $affiliate))
            ->assertRedirect();

        $this->assertSame($oldToken, $affiliate->fresh()->verification_token);
        $this->assertSame(1, DigitalCredential::where('affiliate_id', $affiliate->id)->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'affiliate_credential_files_regenerated', 'auditable_id' => $affiliate->id]);
    }

    public function test_photo_update_invalidates_credential_files(): void
    {
        Storage::fake('public');
        [, $affiliate] = $this->affiliate();
        $credential = $this->credential($affiliate, ['pdf_path' => 'credentials/old.pdf', 'png_path' => 'credentials/old.png']);
        Storage::disk('public')->put('credentials/old.pdf', 'pdf');
        Storage::disk('public')->put('credentials/old.png', 'png');

        $this->actingAs($this->internalUser('secretaria'))->post(route('admin.affiliates.photo.update', $affiliate), [
            'photo' => UploadedFile::fake()->image('photo.jpg', 900, 700),
        ])->assertRedirect();

        $this->assertNotNull($affiliate->fresh()->photo_path);
        $this->assertSame($affiliate->fresh()->photo_path, $affiliate->fresh()->person->photo);
        $this->assertNull($credential->fresh()->pdf_path);
        Storage::disk('public')->assertMissing('credentials/old.pdf');
    }

    public function test_soft_delete_and_restore_preserve_relations_without_reactivating_user_or_credential(): void
    {
        [, $affiliate] = $this->affiliate();
        $credential = $this->credential($affiliate, ['status' => 'suspendida']);
        $affiliate->user->update(['is_active' => false]);
        $admin = $this->internalUser('administrador');

        $this->actingAs($admin)->delete(route('affiliates.destroy', $affiliate), [
            'confirmation' => 'ELIMINAR',
            'deletion_reason' => 'Baja solicitada.',
        ])->assertRedirect();

        $this->assertSoftDeleted('affiliates', ['id' => $affiliate->id]);
        $this->actingAs($admin)->post(route('affiliates.restore', $affiliate->id))->assertRedirect();
        $this->assertDatabaseHas('affiliates', ['id' => $affiliate->id, 'deleted_at' => null]);
        $this->assertFalse($affiliate->user()->withTrashed()->first()->is_active);
        $this->assertSame('suspendida', $credential->fresh()->status);
    }

    public function test_duplicate_alert_timeline_and_audit_are_visible_without_sensitive_values(): void
    {
        [, $affiliate] = $this->affiliate();
        $affiliate->update(['phone' => '77700011']);
        Person::create(['full_name' => 'Duplicado', 'ci' => 'DUP'.Str::random(8), 'phone' => '77700011']);
        AuditLog::create([
            'action' => 'affiliate_sector_changed',
            'auditable_type' => Affiliate::class,
            'auditable_id' => $affiliate->id,
            'metadata' => ['verification_token' => 'secret-token', 'sector' => 'SALUD'],
        ]);

        $this->actingAs($this->internalUser('gerente'))->get(route('affiliates.show', $affiliate))
            ->assertOk()
            ->assertSee('Posible duplicado')
            ->assertSee('Timeline')
            ->assertSee('Auditoria')
            ->assertDontSee('secret-token');
    }

    private function affiliate(string $email = 'affiliate-admin@test.local'): array
    {
        $person = Person::create(['full_name' => 'AFILIADO ADMIN', 'ci' => Str::random(8), 'email' => $email]);
        $sector = Sector::create(['name' => 'MAGISTERIO', 'code' => 'MAG'.Str::upper(Str::random(4)), 'is_active' => true]);
        $plan = AffiliationPlan::create(['name' => 'PLAN BASE', 'affiliation_fee' => 100, 'credential_fee' => 20, 'is_active' => true]);
        $user = User::factory()->create([
            'person_id' => $person->id,
            'name' => 'AFILIADO ADMIN',
            'email' => $email,
            'role' => 'afiliado',
            'user_type' => 'affiliate',
            'is_active' => true,
            'password' => Hash::make('Password1'),
        ]);
        $affiliate = Affiliate::create([
            'user_id' => $user->id,
            'person_id' => $person->id,
            'sector_id' => $sector->id,
            'affiliation_plan_id' => $plan->id,
            'full_name' => 'AFILIADO ADMIN',
            'ci' => $person->ci,
            'email' => $email,
            'registration_number' => 'MAG-'.Str::upper(Str::random(8)),
            'status' => 'activo',
            'verification_token' => (string) Str::uuid(),
        ]);

        return [$user, $affiliate];
    }

    private function internalUser(string $role): User
    {
        return User::factory()->create([
            'name' => mb_strtoupper($role),
            'email' => $role.'-'.Str::random(8).'@test.local',
            'role' => $role,
            'user_type' => 'internal',
            'is_active' => true,
            'password' => Hash::make('Password1'),
        ]);
    }

    private function credential(Affiliate $affiliate, array $attributes = []): DigitalCredential
    {
        return DigitalCredential::create(array_merge([
            'affiliate_id' => $affiliate->id,
            'status' => 'vigente',
            'qr_path' => 'credentials/qr/'.$affiliate->registration_number.'.png',
            'pdf_path' => 'credentials/pdf/'.$affiliate->registration_number.'.pdf',
            'png_path' => 'credentials/png/'.$affiliate->registration_number.'.png',
            'generated_at' => now(),
        ], $attributes));
    }
}
