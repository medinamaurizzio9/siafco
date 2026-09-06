<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Models\AffiliationPayment;
use App\Models\AffiliationPlan;
use App\Models\AuditLog;
use App\Models\DigitalCredential;
use App\Models\Person;
use App\Models\PublicAffiliationRequest;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AffiliateDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_authorized_roles_see_the_delete_control(): void
    {
        $affiliate = $this->affiliate();
        $admin = $this->user('administrador', 'admin-delete@test.local');
        $superadmin = $this->user('superadministrador', 'super-delete@test.local');
        $secretary = $this->user('secretaria', 'secretary-delete@test.local');
        $consultant = $this->user('consulta', 'consultant-delete@test.local');

        $this->actingAs($admin)->get(route('affiliates.index'))
            ->assertOk()
            ->assertSee('data-confirm-title="Eliminar registro"', false)
            ->assertSee('Eliminar definitivamente');

        $this->actingAs($superadmin)->get(route('affiliates.index'))
            ->assertOk()
            ->assertSee('data-confirm-title="Eliminar registro"', false);

        foreach ([$secretary, $consultant] as $user) {
            $this->actingAs($user)->get(route('affiliates.index'))
                ->assertOk()
                ->assertDontSee('data-confirm-title="Eliminar registro"', false);
        }

        $this->assertFalse($affiliate->trashed());
    }

    public function test_confirmed_payment_blocks_physical_deletion_and_preserves_history_and_files(): void
    {
        Storage::fake('public');
        $admin = $this->user('administrador', 'admin-destroy@test.local');
        $affiliate = $this->affiliate();
        Storage::disk('public')->put($affiliate->photo_path, 'photo');
        Storage::disk('public')->put('payments/proof.jpg', 'proof');
        Storage::disk('public')->put('credentials/card.png', 'png');
        Storage::disk('public')->put('credentials/card.pdf', 'pdf');
        Storage::disk('public')->put('credentials/qr.png', 'qr');

        $payment = AffiliationPayment::create([
            'affiliate_id' => $affiliate->id,
            'amount' => 130,
            'voucher_path' => 'payments/proof.jpg',
            'status' => 'confirmado',
        ]);
        $credential = DigitalCredential::create([
            'affiliate_id' => $affiliate->id,
            'png_path' => 'credentials/card.png',
            'pdf_path' => 'credentials/card.pdf',
            'qr_path' => 'credentials/qr.png',
            'generated_at' => now(),
        ]);

        $response = $this->actingAs($admin)
            ->from(route('affiliates.index'))
            ->delete(route('affiliates.destroy', $affiliate));

        $response->assertRedirect(route('affiliates.index'))
            ->assertSessionHas('error', 'Este registro no puede eliminarse porque tiene un pago confirmado.');
        $this->assertDatabaseHas('affiliates', ['id' => $affiliate->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('users', ['id' => $affiliate->user_id, 'deleted_at' => null]);
        $this->assertDatabaseHas('affiliation_payments', ['id' => $payment->id, 'affiliate_id' => $affiliate->id]);
        $this->assertDatabaseHas('digital_credentials', ['id' => $credential->id, 'affiliate_id' => $affiliate->id]);
        Storage::disk('public')->assertExists([
            $affiliate->photo_path,
            'payments/proof.jpg',
            'credentials/card.png',
            'credentials/card.pdf',
            'credentials/qr.png',
        ]);

        $this->assertDatabaseMissing('audit_logs', ['action' => 'affiliate_permanently_deleted']);
    }

    public function test_authorized_user_deletes_public_request_without_payment_and_releases_ci(): void
    {
        $admin = $this->user('administrador', 'admin-public-delete@test.local');
        [$application, $affiliate, $person] = $this->publicApplication();

        $response = $this->actingAs($admin)
            ->get(route('public-affiliation.admin.index'));
        $response->assertOk()
            ->assertSee('data-confirm-title="Eliminar registro"', false)
            ->assertSee('Eliminar definitivamente')
            ->assertDontSee('name="confirmation"', false)
            ->assertDontSee('name="deletion_reason"', false);

        $response = $this->actingAs($admin)
            ->delete(route('public-affiliation.admin.destroy', $application));

        $response->assertRedirect(route('public-affiliation.admin.index'))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('public_affiliation_requests', ['id' => $application->id]);
        $this->assertDatabaseMissing('affiliates', ['id' => $affiliate->id]);
        $this->assertDatabaseMissing('people', ['id' => $person->id]);
        $this->assertDatabaseMissing('people', ['ci' => $person->ci]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'affiliate_permanently_deleted', 'auditable_id' => null]);

        Person::create(['full_name' => 'Nuevo registro', 'ci' => $person->ci]);
        $this->assertDatabaseHas('people', ['ci' => $person->ci]);
    }

    public function test_unauthorized_user_cannot_delete_public_request(): void
    {
        $consultant = $this->user('consulta', 'consulta-public-delete@test.local');
        [$application, $affiliate] = $this->publicApplication();

        $this->actingAs($consultant)
            ->delete(route('public-affiliation.admin.destroy', $application))->assertForbidden();

        $this->assertDatabaseHas('public_affiliation_requests', ['id' => $application->id]);
        $this->assertDatabaseHas('affiliates', ['id' => $affiliate->id]);
    }

    public function test_public_request_with_under_review_payment_returns_controlled_error(): void
    {
        $admin = $this->user('administrador', 'admin-review-delete@test.local');
        [$application, $affiliate] = $this->publicApplication('under_review');

        $this->actingAs($admin)->from(route('public-affiliation.admin.index'))
            ->delete(route('public-affiliation.admin.destroy', $application))
            ->assertRedirect(route('public-affiliation.admin.index'))
            ->assertSessionHas('error', 'Este registro no puede eliminarse porque tiene un pago en revisión. Primero debe rechazarse o anularse.');

        $this->assertDatabaseHas('affiliates', ['id' => $affiliate->id]);
        $this->assertDatabaseHas('affiliation_payments', ['affiliate_id' => $affiliate->id, 'status' => 'under_review']);
    }

    public function test_public_request_with_confirmed_payment_returns_controlled_error(): void
    {
        $admin = $this->user('administrador', 'admin-confirmed-delete@test.local');
        [$application, $affiliate] = $this->publicApplication('confirmed');

        $this->actingAs($admin)->from(route('public-affiliation.admin.index'))
            ->delete(route('public-affiliation.admin.destroy', $application))
            ->assertRedirect(route('public-affiliation.admin.index'))
            ->assertSessionHas('error', 'Este registro no puede eliminarse porque tiene un pago confirmado.');

        $this->assertDatabaseHas('affiliates', ['id' => $affiliate->id]);
        $this->assertDatabaseHas('affiliation_payments', ['affiliate_id' => $affiliate->id, 'status' => 'confirmed']);
    }

    public function test_rejected_and_voided_payments_allow_physical_deletion(): void
    {
        $admin = $this->user('administrador', 'admin-terminal-delete@test.local');

        foreach (['rejected', 'voided'] as $status) {
            [$application, $affiliate, $person] = $this->publicApplication($status);

            $this->actingAs($admin)
                ->delete(route('public-affiliation.admin.destroy', $application))
                ->assertRedirect(route('public-affiliation.admin.index'))
                ->assertSessionHasNoErrors();

            $this->assertDatabaseMissing('affiliates', ['id' => $affiliate->id]);
            $this->assertDatabaseMissing('affiliation_payments', ['affiliate_id' => $affiliate->id]);
            $this->assertDatabaseMissing('people', ['id' => $person->id]);
        }
    }

    public function test_unauthorized_request_cannot_delete_affiliate_and_domain_block_is_controlled(): void
    {
        $affiliate = $this->affiliate();
        $consultant = $this->user('consulta', 'consulta-manual-delete@test.local');
        $admin = $this->user('administrador', 'admin-validation-delete@test.local');

        $this->actingAs($consultant)->delete(route('affiliates.destroy', $affiliate))->assertForbidden();

        $this->actingAs($admin)
            ->from(route('affiliates.index'))
            ->delete(route('affiliates.destroy', $affiliate))
            ->assertRedirect(route('affiliates.index'))
            ->assertSessionHas('error', 'Este afiliado está activo y no puede eliminarse directamente.');

        $this->actingAs($admin)->get(route('affiliates.show', $affiliate))->assertOk();
        $this->assertDatabaseHas('affiliates', ['id' => $affiliate->id, 'deleted_at' => null]);
    }

    private function affiliate(): Affiliate
    {
        $sector = Sector::firstOrCreate(
            ['code' => 'MAG-RUR'],
            ['name' => 'Magisterio Rural', 'current_sequence' => 1, 'is_active' => true]
        );
        $plan = AffiliationPlan::firstOrCreate(
            ['name' => 'Plan inicial'],
            ['affiliation_fee' => 100, 'credential_fee' => 30, 'is_active' => true]
        );
        $user = User::create([
            'name' => 'Afiliado Eliminable',
            'email' => fake()->unique()->safeEmail(),
            'role' => 'afiliado',
            'password' => Hash::make('affiliate-password'),
        ]);

        return Affiliate::create([
            'user_id' => $user->id,
            'sector_id' => $sector->id,
            'affiliation_plan_id' => $plan->id,
            'full_name' => 'Afiliado Eliminable',
            'ci' => fake()->unique()->numerify('#######'),
            'email' => $user->email,
            'photo_path' => 'affiliates/photos/affiliate.jpg',
            'registration_number' => 'MAG-RUR-000321',
            'status' => 'activo',
            'verification_token' => fake()->uuid(),
        ]);
    }

    private function user(string $role, string $email): User
    {
        return User::create([
            'name' => ucfirst($role),
            'email' => $email,
            'role' => $role,
            'password' => Hash::make('secret'),
        ]);
    }

    private function publicApplication(?string $paymentStatus = null): array
    {
        $sector = Sector::firstOrCreate(
            ['code' => 'PUB-DEL'],
            ['name' => 'Sector público', 'current_sequence' => 0, 'is_active' => true]
        );
        $plan = AffiliationPlan::firstOrCreate(
            ['name' => 'Plan público eliminable'],
            ['affiliation_fee' => 100, 'credential_fee' => 0, 'is_active' => true]
        );
        $person = Person::create([
            'full_name' => 'Solicitud Eliminable',
            'ci' => fake()->unique()->numerify('9######'),
            'email' => fake()->unique()->safeEmail(),
        ]);
        $affiliate = Affiliate::create([
            'person_id' => $person->id,
            'sector_id' => $sector->id,
            'affiliation_plan_id' => $plan->id,
            'full_name' => $person->full_name,
            'ci' => $person->ci,
            'email' => $person->email,
            'registration_number' => null,
            'status' => 'pendiente_pago',
            'verification_token' => fake()->uuid(),
        ]);
        $application = PublicAffiliationRequest::create([
            'person_id' => $person->id,
            'affiliate_id' => $affiliate->id,
            'sector_id' => $sector->id,
            'affiliation_plan_id' => $plan->id,
            'public_token' => fake()->uuid(),
            'request_code' => 'SOL-'.fake()->unique()->numerify('######'),
            'amount_due' => 100,
            'status' => $paymentStatus ? 'payment_submitted' : 'pending_payment',
            'submitted_at' => now(),
        ]);

        if ($paymentStatus) {
            AffiliationPayment::create([
                'affiliate_id' => $affiliate->id,
                'public_affiliation_request_id' => $application->id,
                'affiliation_plan_id' => $plan->id,
                'amount' => 100,
                'status' => $paymentStatus,
            ]);
        }

        return [$application, $affiliate, $person];
    }
}
