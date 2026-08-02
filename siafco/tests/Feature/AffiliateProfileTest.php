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

class AffiliateProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_affiliate_can_view_profile_with_locked_fields_and_without_payments(): void
    {
        [$user, $affiliate] = $this->affiliate();

        $this->actingAs($user)->get(route('affiliate.profile.show'))
            ->assertOk()
            ->assertSee('Mi perfil')
            ->assertSee('Información personal')
            ->assertDontSee('>MI PERFIL<', false)
            ->assertSee('data-crop-modal', false)
            ->assertSee('aria-hidden="true"', false)
            ->assertSee('data-crop-zoom', false)
            ->assertSee('APLICAR RECORTE')
            ->assertSee($affiliate->full_name)
            ->assertSee($affiliate->ci)
            ->assertSee($affiliate->registration_number)
            ->assertSee('solo pueden ser corregidos por Secretaría')
            ->assertSee('No tienes pagos registrados.')
            ->assertDontSee('name="full_name"', false)
            ->assertDontSee('name="ci"', false)
            ->assertDontSee('name="registration_number"', false);
    }

    public function test_affiliate_updates_only_allowed_contact_fields_and_audit_is_recorded(): void
    {
        [$user, $affiliate] = $this->affiliate();

        $this->actingAs($user)->patch(route('affiliate.profile.update'), [
            'phone' => ' +591 700-12345 ',
            'email' => 'NUEVO@EXAMPLE.COM',
            'address' => '  Calle Principal  123 ',
            'birth_date' => '1990-05-10',
            'marital_status' => 'CASADO',
        ])->assertRedirect(route('affiliate.profile.show'));

        $affiliate->refresh();
        $this->assertSame('+591 700-12345', $affiliate->phone);
        $this->assertSame('nuevo@example.com', $affiliate->email);
        $this->assertSame('Calle Principal 123', $affiliate->address);
        $this->assertSame('CASADO', $affiliate->marital_status);
        $this->assertSame('nuevo@example.com', $affiliate->user->email);
        $this->assertSame('nuevo@example.com', $affiliate->person->email);
        $this->assertSame('CASADO', $affiliate->person->marital_status);

        $audit = AuditLog::where('action', 'affiliate_profile_updated')->firstOrFail();
        $this->assertSame($affiliate->id, $audit->auditable_id);
        $this->assertContains('phone', $audit->metadata['fields']);
        $this->assertFalse($audit->metadata['photo_changed']);
    }

    public function test_institutional_field_manipulation_returns_422_and_changes_nothing(): void
    {
        [$user, $affiliate] = $this->affiliate();
        $original = $affiliate->only(['full_name', 'ci', 'registration_number', 'status', 'sector_id', 'regional']);

        $this->actingAs($user)->patch(route('affiliate.profile.update'), [
            'email' => $affiliate->email,
            'full_name' => 'Nombre alterado',
            'ci' => '000000',
            'registration_number' => 'ALTERADO',
            'status' => 'activo',
            'sector_id' => 999,
            'regional' => 'Otra',
        ])->assertSessionHasErrors('profile');

        $this->assertSame($original, $affiliate->fresh()->only(array_keys($original)));
        $this->assertDatabaseMissing('audit_logs', ['action' => 'affiliate_profile_updated']);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'affiliate_profile_institutional_change_rejected',
            'auditable_id' => $affiliate->id,
        ]);
    }

    public function test_photo_replaces_old_file_and_invalidates_only_credential_exports(): void
    {
        Storage::fake('public');
        [$user, $affiliate] = $this->affiliate();
        Storage::disk('public')->put('affiliates/photos/old.jpg', 'old');
        Storage::disk('public')->put('credentials/qr/current.png', 'qr');
        Storage::disk('public')->put('credentials/pdf/current.pdf', 'pdf');
        Storage::disk('public')->put('credentials/png/current.png', 'png');
        $affiliate->update(['photo_path' => 'affiliates/photos/old.jpg']);
        $affiliate->person->update(['photo' => 'affiliates/photos/old.jpg']);
        DigitalCredential::create([
            'affiliate_id' => $affiliate->id,
            'qr_path' => 'credentials/qr/current.png',
            'pdf_path' => 'credentials/pdf/current.pdf',
            'png_path' => 'credentials/png/current.png',
            'generated_at' => now(),
        ]);

        $this->actingAs($user)->patch(route('affiliate.profile.update'), [
            'email' => $affiliate->email,
            'phone' => $affiliate->phone,
            'address' => $affiliate->address,
            'birth_date' => null,
            'marital_status' => null,
            'photo' => UploadedFile::fake()->image('photo.png', 800, 600)->size(800),
        ])->assertRedirect();

        $affiliate->refresh();
        $this->assertStringStartsWith('affiliates/photos/', $affiliate->photo_path);
        $this->assertSame($affiliate->photo_path, $affiliate->person->photo);
        Storage::disk('public')->assertMissing('affiliates/photos/old.jpg');
        Storage::disk('public')->assertExists($affiliate->photo_path);
        Storage::disk('public')->assertExists('credentials/qr/current.png');
        Storage::disk('public')->assertMissing('credentials/pdf/current.pdf');
        Storage::disk('public')->assertMissing('credentials/png/current.png');
        $this->assertNull($affiliate->credential->pdf_path);
        $this->assertNull($affiliate->credential->png_path);
    }

    public function test_affiliate_sees_only_own_paginated_payments_in_descending_order(): void
    {
        [$user, $affiliate] = $this->affiliate();
        [, $other] = $this->affiliate('other@example.com', 'OTR-000002');
        AffiliationPayment::create([
            'affiliate_id' => $affiliate->id, 'amount' => 100,
            'transaction_number' => 'OWN-OLD', 'payment_date' => '2026-01-01', 'status' => 'pendiente',
        ]);
        AffiliationPayment::create([
            'affiliate_id' => $affiliate->id, 'amount' => 200,
            'transaction_number' => 'OWN-NEW', 'payment_date' => '2026-02-01', 'status' => 'confirmado',
        ]);
        AffiliationPayment::create([
            'affiliate_id' => $other->id, 'amount' => 300,
            'transaction_number' => 'OTHER-PAYMENT', 'payment_date' => '2026-03-01', 'status' => 'confirmado',
        ]);

        $response = $this->actingAs($user)->get(route('affiliate.profile.show'))
            ->assertOk()
            ->assertSee('OWN-OLD')
            ->assertSee('OWN-NEW')
            ->assertDontSee('OTHER-PAYMENT');
        $this->assertTrue(strpos($response->getContent(), 'OWN-NEW') < strpos($response->getContent(), 'OWN-OLD'));
    }

    public function test_receipt_route_is_private_and_rejects_another_affiliates_payment(): void
    {
        Storage::fake('local');
        [$user, $affiliate] = $this->affiliate();
        [, $other] = $this->affiliate('other@example.com', 'OTR-000002');
        Storage::disk('local')->put('affiliation-receipts/own.pdf', '%PDF test');
        Storage::disk('local')->put('affiliation-receipts/other.pdf', '%PDF test');
        $own = AffiliationPayment::create([
            'affiliate_id' => $affiliate->id, 'amount' => 100, 'status' => 'pendiente',
            'voucher_path' => 'affiliation-receipts/own.pdf',
        ]);
        $foreign = AffiliationPayment::create([
            'affiliate_id' => $other->id, 'amount' => 100, 'status' => 'pendiente',
            'voucher_path' => 'affiliation-receipts/other.pdf',
        ]);

        $this->actingAs($user)->get(route('affiliate.profile.payments.receipt', $own))->assertOk();
        $this->actingAs($user)->get(route('affiliate.profile.payments.receipt', $foreign))->assertForbidden();
    }

    public function test_non_affiliate_roles_and_user_without_affiliate_are_handled(): void
    {
        $consultation = User::create([
            'name' => 'Consulta', 'email' => 'consulta@example.com', 'role' => 'consulta',
            'password' => Hash::make('secret'),
        ]);
        $orphan = User::create([
            'name' => 'Sin ficha', 'email' => 'orphan@example.com', 'role' => 'afiliado',
            'password' => Hash::make('secret'),
        ]);

        $this->actingAs($consultation)->get(route('affiliate.profile.show'))->assertForbidden();
        $this->actingAs($orphan)->get(route('affiliate.profile.show'))->assertNotFound();
    }

    public function test_profile_photo_rejects_small_oversized_and_svg_files(): void
    {
        Storage::fake('public');
        [$user, $affiliate] = $this->affiliate();
        $base = ['email' => $affiliate->email];

        $this->actingAs($user)->patch(route('affiliate.profile.update'), $base + [
            'photo' => UploadedFile::fake()->image('small.jpg', 200, 200),
        ])->assertSessionHasErrors('photo');

        $this->actingAs($user)->patch(route('affiliate.profile.update'), $base + [
            'photo' => UploadedFile::fake()->image('large.jpg', 600, 600)->size(5200),
        ])->assertSessionHasErrors('photo');

        $this->actingAs($user)->patch(route('affiliate.profile.update'), $base + [
            'photo' => UploadedFile::fake()->createWithContent(
                'vector.svg',
                '<svg xmlns="http://www.w3.org/2000/svg" width="600" height="600"></svg>'
            ),
        ])->assertSessionHasErrors('photo');
    }

    public function test_shared_cropper_defines_square_crop_zoom_reset_and_600_pixel_jpeg(): void
    {
        $script = file_get_contents(resource_path('js/components/photo-cropper.js'));

        $this->assertStringContainsString('aspectRatio: 1', $script);
        $this->assertStringContainsString("dragMode: 'move'", $script);
        $this->assertStringContainsString('cropBoxResizable: true', $script);
        $this->assertStringContainsString('data-crop-zoom', file_get_contents(resource_path('views/components/forms/photo-cropper.blade.php')));
        $this->assertStringContainsString('cropper?.reset()', $script);
        $this->assertStringContainsString('width: 600', $script);
        $this->assertStringContainsString('height: 600', $script);
        $this->assertStringContainsString("'image/jpeg', 0.9", $script);
        $this->assertStringContainsString('new DataTransfer()', $script);
    }

    public function test_affiliate_panel_shows_real_summary_and_shared_credential_thumbnail(): void
    {
        [$user, $affiliate] = $this->affiliate();
        AffiliationPayment::create([
            'affiliate_id' => $affiliate->id,
            'amount' => 120,
            'transaction_number' => 'PAY-OLD',
            'payment_date' => '2026-06-01',
            'status' => 'confirmado',
        ]);
        AffiliationPayment::create([
            'affiliate_id' => $affiliate->id,
            'amount' => 150,
            'transaction_number' => 'PAY-NEW',
            'payment_date' => '2026-07-28',
            'status' => 'confirmado',
        ]);
        DigitalCredential::create([
            'affiliate_id' => $affiliate->id,
            'qr_path' => 'credentials/qr/test.png',
            'generated_at' => now(),
        ]);

        $this->actingAs($user)->get(route('affiliate.panel'))
            ->assertOk()
            ->assertSee($affiliate->full_name)
            ->assertSee($affiliate->registration_number)
            ->assertSee($affiliate->sector->name)
            ->assertSee($affiliate->regional)
            ->assertSee('Pagos registrados')
            ->assertSee('28/07/2026')
            ->assertSee('credential-card--thumbnail', false)
            ->assertSee('VER MI CREDENCIAL')
            ->assertSee('Panel principal')
            ->assertSee('Mis pagos');
    }

    private function affiliate(
        string $email = 'affiliate@example.com',
        string $registration = 'MAG-RUR-000001'
    ): array {
        $sector = Sector::create([
            'name' => 'Magisterio Rural '.Str::random(5),
            'code' => 'SEC-'.Str::upper(Str::random(5)),
            'regional' => 'La Paz',
            'institution' => 'Cooperativa Tierra Bendita',
            'is_active' => true,
        ]);
        $plan = AffiliationPlan::create([
            'name' => 'Plan '.Str::random(5), 'affiliation_fee' => 100,
            'credential_fee' => 20, 'currency' => 'BOB', 'is_active' => true,
        ]);
        $person = Person::create([
            'full_name' => 'Claudia Marisela Pacheco Toro',
            'ci' => (string) random_int(1000000, 9999999),
            'phone' => '70000000', 'email' => $email, 'address' => 'La Paz',
        ]);
        $user = User::create([
            'person_id' => $person->id,
            'name' => $person->full_name, 'email' => $email, 'role' => 'afiliado',
            'password' => Hash::make('secret'),
        ]);
        $affiliate = Affiliate::create([
            'user_id' => $user->id, 'person_id' => $person->id,
            'sector_id' => $sector->id, 'affiliation_plan_id' => $plan->id,
            'full_name' => $person->full_name, 'ci' => $person->ci,
            'phone' => $person->phone, 'email' => $email, 'address' => $person->address,
            'regional' => 'La Paz', 'institution' => 'Cooperativa Tierra Bendita',
            'registration_number' => $registration, 'status' => 'activo',
            'verification_token' => (string) Str::uuid(),
        ]);

        return [$user, $affiliate];
    }
}
