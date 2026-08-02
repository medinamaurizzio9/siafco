<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Models\AffiliationPlan;
use App\Models\DigitalCredential;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MobileApiCredentialTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_gets_uniform_401(): void
    {
        $this->getJson('/api/mobile/v1/me/credential')
            ->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['success', 'message', 'errors']);
    }

    public function test_pending_affiliate_cannot_get_credential(): void
    {
        Storage::fake('public');
        [$user] = $this->affiliate(status: 'pendiente_pago', credential: true);

        $this->withToken($this->tokenFor($user))->getJson('/api/mobile/v1/me/credential')
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.status.0', 'pendiente_pago');
    }

    public function test_suspended_affiliate_cannot_get_credential(): void
    {
        Storage::fake('public');
        [$user] = $this->affiliate(status: 'suspendido', credential: true);

        $this->withToken($this->tokenFor($user))->getJson('/api/mobile/v1/me/credential')
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }

    public function test_observed_affiliate_cannot_get_credential(): void
    {
        Storage::fake('public');
        [$user] = $this->affiliate(status: 'observado', credential: true);

        $this->withToken($this->tokenFor($user))->getJson('/api/mobile/v1/me/credential')
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }

    public function test_active_affiliate_without_credential_gets_uniform_404(): void
    {
        Storage::fake('public');
        [$user] = $this->affiliate(status: 'activo', credential: false);

        $this->withToken($this->tokenFor($user))->getJson('/api/mobile/v1/me/credential')
            ->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['success', 'message', 'errors']);
    }

    public function test_active_affiliate_with_missing_qr_file_gets_uniform_404(): void
    {
        Storage::fake('public');
        [$user] = $this->affiliate(status: 'activo', credential: true, qrFile: false);

        $this->withToken($this->tokenFor($user))->getJson('/api/mobile/v1/me/credential')
            ->assertNotFound()
            ->assertJsonPath('success', false);
    }

    public function test_active_affiliate_gets_own_mobile_credential_json(): void
    {
        Storage::fake('public');
        [$user, $affiliate] = $this->affiliate(status: 'activo', credential: true);

        $response = $this->withToken($this->tokenFor($user))
            ->getJson('/api/mobile/v1/me/credential?affiliate_id=999&user_id=999&ci=ignore-me');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Credencial movil disponible.')
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'credential' => [
                        'institution_name',
                        'affiliate_name',
                        'registration_number',
                        'sector',
                        'regional',
                        'status',
                        'status_label',
                        'issued_at',
                        'verification_url',
                        'qr_image',
                    ],
                ],
            ])
            ->assertJsonPath('data.credential.affiliate_name', 'AFILIADO MOVIL')
            ->assertJsonPath('data.credential.registration_number', $affiliate->registration_number)
            ->assertJsonPath('data.credential.status', 'activo')
            ->assertJsonPath('data.credential.verification_url', route('verify.show', $affiliate->verification_token));

        $payload = $response->json();
        $this->assertStringStartsWith('data:image/png;base64,', $payload['data']['credential']['qr_image']);
        $this->assertCredentialPayloadDoesNotExposeSensitiveFields($payload, $affiliate);
    }

    public function test_mobile_credential_is_isolated_between_affiliates(): void
    {
        Storage::fake('public');
        [$user, $affiliate] = $this->affiliate(status: 'activo', credential: true);
        [, $other] = $this->affiliate(
            email: 'other-credential@siafco.test',
            ci: '90071002',
            registrationNumber: 'REG-B',
            status: 'activo',
            credential: true
        );

        $response = $this->withToken($this->tokenFor($user))
            ->getJson('/api/mobile/v1/me/credential?affiliate_id='.$other->id);

        $response->assertOk()
            ->assertJsonPath('data.credential.registration_number', $affiliate->registration_number);

        $content = $response->getContent();
        $this->assertStringNotContainsString($other->registration_number, $content);
        $this->assertStringNotContainsString($other->verification_token, $content);
    }

    public function test_mobile_credential_does_not_expose_ids_or_personal_contact_fields(): void
    {
        Storage::fake('public');
        [$user, $affiliate] = $this->affiliate(status: 'activo', credential: true);

        $response = $this->withToken($this->tokenFor($user))->getJson('/api/mobile/v1/me/credential');

        $response->assertOk()
            ->assertJsonMissingPath('data.credential.id')
            ->assertJsonMissingPath('data.credential.affiliate_id')
            ->assertJsonMissingPath('data.credential.user_id')
            ->assertJsonMissingPath('data.credential.ci')
            ->assertJsonMissingPath('data.credential.phone')
            ->assertJsonMissingPath('data.credential.email')
            ->assertJsonMissingPath('data.credential.address')
            ->assertJsonMissingPath('data.credential.birth_date')
            ->assertJsonMissingPath('data.credential.verification_token')
            ->assertJsonMissingPath('data.credential.public_token')
            ->assertJsonMissingPath('data.credential.qr_path')
            ->assertJsonMissingPath('data.credential.pdf_path')
            ->assertJsonMissingPath('data.credential.png_path');

        $this->assertCredentialPayloadDoesNotExposeSensitiveFields($response->json(), $affiliate);
    }

    public function test_internal_user_cannot_get_mobile_credential(): void
    {
        $internal = User::factory()->create([
            'role' => 'administrador',
            'user_type' => 'internal',
            'is_active' => true,
        ]);

        $this->withToken($this->tokenFor($internal))->getJson('/api/mobile/v1/me/credential')
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }

    public function test_rate_limit_uses_uniform_mobile_json(): void
    {
        Storage::fake('public');
        [$user] = $this->affiliate(status: 'activo', credential: true);
        $token = $this->tokenFor($user);

        for ($attempt = 0; $attempt < 20; $attempt++) {
            $this->withToken($token)->getJson('/api/mobile/v1/me/credential')->assertOk();
        }

        $this->withToken($token)->getJson('/api/mobile/v1/me/credential')
            ->assertStatus(429)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['success', 'message', 'errors']);
    }

    private function tokenFor(User $user): string
    {
        return $user->refresh()->createToken('mobile-test', ['mobile'])->plainTextToken;
    }

    private function affiliate(
        string $email = 'mobile-credential@siafco.test',
        string $ci = '90071001',
        string $registrationNumber = 'REG-A',
        string $status = 'activo',
        bool $credential = true,
        bool $qrFile = true
    ): array {
        $sector = Sector::create([
            'name' => 'Magisterio Rural',
            'code' => 'MAG-'.substr($ci, -3),
            'is_active' => true,
        ]);
        $plan = AffiliationPlan::create([
            'name' => 'Plan '.$registrationNumber,
            'affiliation_fee' => 100,
            'credential_fee' => 30,
            'is_active' => true,
        ]);
        $user = User::factory()->create([
            'name' => 'Afiliado Movil',
            'email' => $email,
            'password' => Hash::make('Secret1234'),
            'role' => 'afiliado',
            'user_type' => 'affiliate',
            'is_active' => true,
        ]);

        $affiliate = Affiliate::create([
            'user_id' => $user->id,
            'sector_id' => $sector->id,
            'affiliation_plan_id' => $plan->id,
            'full_name' => 'Afiliado Movil',
            'ci' => $ci,
            'phone' => '70000000',
            'email' => $email,
            'address' => 'Direccion privada',
            'institution' => 'Institucion educativa',
            'regional' => 'La Paz',
            'registration_number' => $registrationNumber,
            'status' => $status,
            'verification_token' => fake()->uuid(),
        ]);

        if ($credential) {
            $qrPath = "credentials/qr/{$registrationNumber}.png";
            if ($qrFile) {
                Storage::disk('public')->put($qrPath, $this->png());
            }
            DigitalCredential::create([
                'affiliate_id' => $affiliate->id,
                'qr_path' => $qrPath,
                'pdf_path' => "credentials/pdf/{$registrationNumber}.pdf",
                'png_path' => null,
                'generated_at' => now(),
            ]);
        }

        return [$user->fresh('affiliate'), $affiliate->fresh()];
    }

    private function assertCredentialPayloadDoesNotExposeSensitiveFields(array $payload, Affiliate $affiliate): void
    {
        $json = json_encode($payload);

        $this->assertStringNotContainsString($affiliate->ci, $json);
        $this->assertStringNotContainsString($affiliate->phone, $json);
        $this->assertStringNotContainsString($affiliate->email, $json);
        $this->assertStringNotContainsString($affiliate->address, $json);
        $this->assertStringNotContainsString('storage/app', $json);
        $this->assertStringNotContainsString('credentials/qr', $json);
        $this->assertStringNotContainsString('plainTextToken', $json);
    }

    private function png(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=');
    }
}
