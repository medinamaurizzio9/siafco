<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Models\AffiliationPlan;
use App\Models\DigitalCredential;
use App\Models\Sector;
use App\Models\User;
use App\Services\CredentialExportCapabilities;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CredentialAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_affiliate_can_only_view_their_own_credential_without_export_controls(): void
    {
        Storage::fake('public');
        [$user, $affiliate] = $this->affiliate('propia@test.local', 'REG-000001');
        [, $otherAffiliate] = $this->affiliate('otra@test.local', 'REG-000002');

        $this->actingAs($user)->get(route('affiliate.credential.preview'))
            ->assertOk()
            ->assertSee('MI CREDENCIAL')
            ->assertSee('Volver al panel')
            ->assertDontSee('Descargar PDF')
            ->assertDontSee('Descargar PNG')
            ->assertDontSee('Imprimir credencial');

        $this->actingAs($user)->get(route('credenciales.show', $otherAffiliate))->assertForbidden();
        $this->actingAs($user)->get(route('affiliate.credential.pdf'))->assertForbidden();
        $this->actingAs($user)->get(route('affiliate.credential.png'))->assertForbidden();
        $this->actingAs($user)->get(route('credentials.print', $affiliate))->assertForbidden();
    }

    public function test_administrator_superadministrator_and_secretary_can_export_credentials(): void
    {
        Storage::fake('public');
        $capabilities = $this->mock(CredentialExportCapabilities::class);
        $capabilities->shouldReceive('canExportPng')->andReturnTrue();
        [, $affiliate] = $this->affiliate('titular@test.local', 'REG-000003');

        foreach (['administrador', 'superadministrador', 'secretaria'] as $role) {
            $user = User::create([
                'name' => mb_strtoupper($role),
                'email' => $role.'@test.local',
                'role' => $role,
                'password' => Hash::make('secret123'),
            ]);

            $this->actingAs($user)->get(route('credentials.preview', $affiliate))
                ->assertOk()
                ->assertSee('Descargar PDF')
                ->assertSee('Descargar PNG')
                ->assertSee('Imprimir credencial');
            $this->actingAs($user)->get(route('credentials.pdf', $affiliate))
                ->assertDownload('credencial-REG-000003.pdf');
            $this->actingAs($user)->get(route('credentials.png', $affiliate))
                ->assertDownload('credencial-REG-000003.png');
            $printResponse = $this->actingAs($user)->get(route('credentials.print', $affiliate));
            $printResponse->assertOk();
            if ($role === 'administrador') {
                $printResponse
                    ->assertSee('@page {', false)
                    ->assertSee('size: 85.6mm 53.98mm', false)
                    ->assertSee('credential-print-page', false)
                    ->assertSee('credential-card--print', false)
                    ->assertSee('document.fonts?.ready', false)
                    ->assertDontSee('data-sidebar-shell', false)
                    ->assertDontSee('min-height: 100vh', false)
                    ->assertDontSee('transform: scale(', false)
                    ->assertDontSee('class="credential-actions"', false);
            }
        }
    }

    public function test_png_unavailability_is_controlled_and_only_visible_to_administration(): void
    {
        Storage::fake('public');
        $capabilities = $this->mock(CredentialExportCapabilities::class);
        $capabilities->shouldReceive('canExportPng')->andReturnFalse();
        $capabilities->shouldReceive('canExportPdf')->andReturnTrue();
        $capabilities->shouldReceive('canPrintCredential')->andReturnTrue();
        $capabilities->shouldReceive('pdfEngine')->andReturn('Dompdf');
        $capabilities->shouldReceive('pngEngine')->andReturn('No disponible');
        $capabilities->shouldReceive('pngUnavailableReason')
            ->andReturn('Este servidor no dispone de Chrome/Chromium funcional.');

        [$affiliateUser, $affiliate] = $this->affiliate('sin-png@test.local', 'REG-000005');
        $administrator = User::create([
            'name' => 'ADMINISTRADOR',
            'email' => 'admin-png@test.local',
            'role' => 'administrador',
            'password' => Hash::make('secret123'),
        ]);
        $secretary = User::create([
            'name' => 'SECRETARÍA',
            'email' => 'secretaria-png@test.local',
            'role' => 'secretaria',
            'password' => Hash::make('secret123'),
        ]);

        $this->actingAs($administrator)->get(route('credentials.preview', $affiliate))
            ->assertOk()
            ->assertSee('PNG no disponible en este servidor.')
            ->assertDontSee('Descargar PNG');
        $this->actingAs($administrator)->from(route('credentials.preview', $affiliate))
            ->get(route('credentials.png', $affiliate))
            ->assertRedirect(route('credentials.preview', $affiliate))
            ->assertSessionHas('warning', 'La descarga PNG no está disponible en este servidor. Utilice la descarga PDF.');
        $this->actingAs($secretary)->from(route('credentials.preview', $affiliate))
            ->get(route('credentials.png', $affiliate))
            ->assertRedirect(route('credentials.preview', $affiliate))
            ->assertSessionHas('warning', 'La descarga PNG no está disponible en este servidor. Utilice la descarga PDF.');
        $this->actingAs($administrator)->getJson(route('credentials.png', $affiliate))
            ->assertStatus(503)
            ->assertJsonPath('message', 'La descarga PNG no está disponible en este servidor. Utilice la descarga PDF.');
        $this->actingAs($administrator)->get(route('institutional-settings.edit'))
            ->assertOk()
            ->assertSee('Exportación de credenciales')
            ->assertSee('Este servidor no dispone de Chrome/Chromium funcional.');

        $this->actingAs($affiliateUser)->get(route('affiliate.credential.preview'))
            ->assertOk()
            ->assertDontSee('PNG no disponible en este servidor.')
            ->assertDontSee('Chrome/Chromium');
        $this->actingAs($affiliateUser)->get(route('affiliate.credential.png'))->assertForbidden();
    }

    public function test_consultation_user_cannot_view_or_export_credentials(): void
    {
        Storage::fake('public');
        [, $affiliate] = $this->affiliate('consulta-titular@test.local', 'REG-000004');
        $consultation = User::create([
            'name' => 'CONSULTA',
            'email' => 'consulta@test.local',
            'role' => 'consulta',
            'password' => Hash::make('secret123'),
        ]);

        $this->actingAs($consultation)->get(route('credenciales.show', $affiliate))->assertForbidden();
        $this->actingAs($consultation)->get(route('credenciales.pdf', $affiliate))->assertForbidden();
        $this->actingAs($consultation)->get(route('credentials.png', $affiliate))->assertForbidden();
        $this->actingAs($consultation)->get(route('credentials.print', $affiliate))->assertForbidden();
    }

    private function affiliate(string $email, string $registrationNumber): array
    {
        $sector = Sector::firstOrCreate(
            ['code' => 'MAG-RUR'],
            ['name' => 'Educación Ñandutí', 'current_sequence' => 1, 'is_active' => true]
        );
        $plan = AffiliationPlan::firstOrCreate(
            ['name' => 'Inicial'],
            ['affiliation_fee' => 100, 'credential_fee' => 30, 'is_active' => true]
        );
        $user = User::create([
            'name' => 'AFILIADO',
            'email' => $email,
            'role' => 'afiliado',
            'password' => Hash::make('secret123'),
        ]);
        $affiliate = Affiliate::create([
            'user_id' => $user->id,
            'sector_id' => $sector->id,
            'affiliation_plan_id' => $plan->id,
            'full_name' => 'ÁNGELA MUÑOZ DE LA PEÑA',
            'ci' => 'CI-'.$registrationNumber,
            'email' => $email,
            'institution' => 'INSTITUCIÓN EDUCATIVA COOPERATIVA',
            'regional' => 'LA PAZ',
            'registration_number' => $registrationNumber,
            'status' => 'activo',
            'verification_token' => fake()->uuid(),
        ]);

        foreach (['pdf', 'png', 'qr'] as $format) {
            Storage::disk('public')->put("credentials/test/{$registrationNumber}.{$format}", 'credential-file');
        }

        DigitalCredential::create([
            'affiliate_id' => $affiliate->id,
            'pdf_path' => "credentials/test/{$registrationNumber}.pdf",
            'png_path' => "credentials/test/{$registrationNumber}.png",
            'qr_path' => "credentials/test/{$registrationNumber}.qr",
            'generated_at' => now()->addDay(),
        ]);

        return [$user, $affiliate];
    }
}
