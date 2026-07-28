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
            $this->actingAs($user)->get(route('credentials.print', $affiliate))->assertOk();
        }
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
