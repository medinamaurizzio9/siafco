<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Models\AffiliationPayment;
use App\Models\AffiliationPlan;
use App\Models\AuditLog;
use App\Models\DigitalCredential;
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
            ->assertSee('data-delete-affiliate-trigger', false);

        $this->actingAs($superadmin)->get(route('affiliates.index'))
            ->assertOk()
            ->assertSee('data-delete-affiliate-trigger', false);

        foreach ([$secretary, $consultant] as $user) {
            $this->actingAs($user)->get(route('affiliates.index'))
                ->assertOk()
                ->assertDontSee('data-delete-affiliate-trigger', false);
        }

        $this->assertFalse($affiliate->trashed());
    }

    public function test_administrator_soft_deletes_affiliate_and_linked_user_but_preserves_history_and_files(): void
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
            ->delete(route('affiliates.destroy', $affiliate), [
                'confirmation' => 'ELIMINAR',
                'deletion_reason' => 'Baja institucional solicitada.',
            ]);

        $response->assertRedirect(route('affiliates.index'))
            ->assertSessionHas('status', 'El afiliado fue eliminado correctamente.');
        $this->assertSoftDeleted('affiliates', ['id' => $affiliate->id]);
        $this->assertSoftDeleted('users', ['id' => $affiliate->user_id]);
        $this->assertDatabaseHas('affiliation_payments', ['id' => $payment->id, 'affiliate_id' => $affiliate->id]);
        $this->assertDatabaseHas('digital_credentials', ['id' => $credential->id, 'affiliate_id' => $affiliate->id]);
        Storage::disk('public')->assertExists([
            $affiliate->photo_path,
            'payments/proof.jpg',
            'credentials/card.png',
            'credentials/card.pdf',
            'credentials/qr.png',
        ]);

        $audit = AuditLog::where('action', 'afiliado.eliminado')->firstOrFail();
        $this->assertSame($admin->id, $audit->user_id);
        $this->assertSame($affiliate->id, $audit->auditable_id);
        $this->assertSame('Baja institucional solicitada.', $audit->metadata['reason']);
        $this->assertSame($affiliate->registration_number, $audit->metadata['registration_number']);

        $this->get(route('verify.show', $affiliate->verification_token))
            ->assertOk()
            ->assertSee('Credencial no válida')
            ->assertSee('El afiliado fue dado de baja o eliminado del sistema.')
            ->assertDontSee('AFILIADO ACTIVO');

        $this->actingAs($admin)->get(route('affiliates.index'))
            ->assertOk()
            ->assertDontSee($affiliate->full_name);

        auth()->logout();
        $this->post(route('login.post'), [
            'email' => $affiliate->email,
            'password' => 'affiliate-password',
        ])->assertSessionHasErrors('email');
    }

    public function test_unauthorized_or_incomplete_requests_cannot_delete_affiliate(): void
    {
        $affiliate = $this->affiliate();
        $consultant = $this->user('consulta', 'consulta-manual-delete@test.local');
        $admin = $this->user('administrador', 'admin-validation-delete@test.local');

        $this->actingAs($consultant)->delete(route('affiliates.destroy', $affiliate), [
            'confirmation' => 'ELIMINAR',
            'deletion_reason' => 'Intento sin autorización.',
        ])->assertForbidden();

        $this->actingAs($admin)
            ->from(route('affiliates.index'))
            ->delete(route('affiliates.destroy', $affiliate), [
                'confirmation' => 'eliminar',
                'deletion_reason' => '',
            ])
            ->assertSessionHasErrors(['confirmation', 'deletion_reason']);

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
}
