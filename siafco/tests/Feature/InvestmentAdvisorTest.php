<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Models\AuditLog;
use App\Models\InvestmentAdvisor;
use App\Models\InvestmentCrmSequence;
use App\Models\Investor;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class InvestmentAdvisorTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->admin = User::create([
            'name' => 'Administración',
            'email' => 'advisor-admin@test.local',
            'role' => 'superadministrador',
            'user_type' => 'internal',
            'is_active' => true,
            'password' => Hash::make('secret'),
        ]);
    }

    public function test_superadministrator_can_list_advisors_and_user_without_permission_cannot(): void
    {
        $this->actingAs($this->admin)
            ->get(route('investments.advisors.index'))
            ->assertOk()
            ->assertSee('Asesores de inversión');

        $unauthorized = User::create([
            'name' => 'Consulta',
            'email' => 'advisor-readonly@test.local',
            'role' => 'consulta',
            'user_type' => 'internal',
            'is_active' => true,
            'password' => Hash::make('secret'),
        ]);

        $this->actingAs($unauthorized)->get(route('investments.advisors.index'))->assertForbidden();
    }

    public function test_creation_generates_consecutive_numbers_secure_identifiers_qr_and_audit_without_domain_coupling(): void
    {
        $this->actingAs($this->admin);

        $this->post(route('investments.advisors.store'), $this->payload('Ana Asesora', '7000 1000'))
            ->assertRedirect();
        $this->post(route('investments.advisors.store'), $this->payload('Bruno Asesor', '+591-7000-2000'))
            ->assertRedirect();

        $first = InvestmentAdvisor::query()->oldest('id')->firstOrFail();
        $second = InvestmentAdvisor::query()->latest('id')->firstOrFail();

        $this->assertSame('ASE-0001', $first->advisor_number);
        $this->assertSame('ASE-0002', $second->advisor_number);
        $this->assertNotSame($first->advisor_number, $first->public_token);
        $this->assertNotSame((string) $first->id, $first->public_token);
        $this->assertNotSame($first->public_token, $second->public_token);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $first->public_id);
        $this->assertSame(64, strlen($first->public_token));
        $this->assertSame('70001000', $first->phone);
        $this->assertSame('+59170002000', $second->phone);
        $this->assertSame(3, InvestmentCrmSequence::firstWhere('key', 'investment_advisor')->next_number);
        Storage::disk('public')->assertExists($first->qrPath());
        Storage::disk('public')->assertExists($second->qrPath());
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'investment_advisor_created',
            'auditable_type' => InvestmentAdvisor::class,
            'auditable_id' => $first->id,
        ]);
        $this->assertSame(0, Affiliate::count());
        $this->assertSame(0, Investor::count());
        $this->assertSame(0, Person::count());

        $audit = AuditLog::firstWhere('action', 'investment_advisor_created');
        $serializedMetadata = json_encode($audit->metadata);
        $this->assertStringNotContainsString($first->public_token, $serializedMetadata);
        $this->assertStringNotContainsString($first->phone, $serializedMetadata);
        $this->assertStringNotContainsString((string) $first->email, $serializedMetadata);
    }

    public function test_public_route_only_exposes_active_advisor_by_valid_token(): void
    {
        $advisor = $this->createAdvisor();

        $this->get(route('investments.advisors.public', $advisor->public_token))
            ->assertOk()
            ->assertSee($advisor->full_name)
            ->assertSee($advisor->advisor_number)
            ->assertDontSee($advisor->phone)
            ->assertDontSee($advisor->email)
            ->assertDontSee($advisor->public_id)
            ->assertDontSee($advisor->public_token);

        $this->get(route('investments.advisors.public', 'invalid-token'))->assertNotFound();

        $advisor->update(['is_active' => false]);
        $this->get(route('investments.advisors.public', $advisor->public_token))->assertNotFound();
    }

    public function test_authorized_user_can_edit_without_changing_protected_identifiers(): void
    {
        $advisor = $this->createAdvisor();
        $originalNumber = $advisor->advisor_number;
        $originalToken = $advisor->public_token;
        $originalPublicId = $advisor->public_id;

        $this->actingAs($this->admin)
            ->put(route('investments.advisors.update', $advisor), [
                ...$this->payload('Nombre Actualizado', '7654-3210'),
                'advisor_number' => 'ASE-9999',
                'public_id' => '00000000-0000-0000-0000-000000000000',
                'public_token' => 'manipulated-token',
            ])
            ->assertRedirect(route('investments.advisors.show', $advisor));

        $advisor->refresh();
        $this->assertSame('Nombre Actualizado', $advisor->full_name);
        $this->assertSame('76543210', $advisor->phone);
        $this->assertSame($originalNumber, $advisor->advisor_number);
        $this->assertSame($originalToken, $advisor->public_token);
        $this->assertSame($originalPublicId, $advisor->public_id);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'investment_advisor_updated',
            'auditable_id' => $advisor->id,
        ]);
    }

    public function test_state_change_has_specific_audit_and_qr_can_be_downloaded(): void
    {
        $advisor = $this->createAdvisor();

        $this->actingAs($this->admin)
            ->put(route('investments.advisors.update', $advisor), [
                ...$this->payload($advisor->full_name, $advisor->phone),
                'is_active' => false,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'investment_advisor_deactivated',
            'auditable_id' => $advisor->id,
        ]);

        $this->get(route('investments.advisors.qr.download', $advisor))->assertOk();
    }

    public function test_linked_user_can_only_represent_one_advisor(): void
    {
        $linkedUser = User::create([
            'name' => 'Asesor interno',
            'email' => 'linked-advisor@test.local',
            'role' => 'gerente',
            'user_type' => 'internal',
            'is_active' => true,
            'password' => Hash::make('secret'),
        ]);

        $this->actingAs($this->admin)
            ->post(route('investments.advisors.store'), [...$this->payload(), 'user_id' => $linkedUser->id])
            ->assertRedirect();

        $this->post(route('investments.advisors.store'), [...$this->payload('Otro asesor', '71112222'), 'user_id' => $linkedUser->id])
            ->assertSessionHasErrors('user_id');
    }

    public function test_advisor_photo_is_validated_stored_displayed_replaced_and_removed(): void
    {
        $this->actingAs($this->admin)->post(route('investments.advisors.store'), [
            ...$this->payload(),
            'photo' => UploadedFile::fake()->image('advisor.jpg', 640, 800),
        ])->assertRedirect();

        $advisor = InvestmentAdvisor::firstOrFail();
        $firstPath = $advisor->photo_path;
        $this->assertNotNull($firstPath);
        $this->assertStringStartsWith('investments/advisors/photos/', $firstPath);
        Storage::disk('public')->assertExists($firstPath);
        $this->get(route('investments.advisors.public', $advisor->public_token))
            ->assertOk()->assertSee($advisor->photoUrl(), false);

        $this->put(route('investments.advisors.update', $advisor), [
            ...$this->payload(),
            'photo' => UploadedFile::fake()->image('replacement.png', 700, 500),
        ])->assertRedirect();
        $advisor->refresh();
        $this->assertNotSame($firstPath, $advisor->photo_path);
        Storage::disk('public')->assertMissing($firstPath);
        Storage::disk('public')->assertExists($advisor->photo_path);

        $lastPath = $advisor->photo_path;
        $this->put(route('investments.advisors.update', $advisor), [
            ...$this->payload(), 'remove_photo' => true,
        ])->assertRedirect();
        $this->assertNull($advisor->refresh()->photo_path);
        Storage::disk('public')->assertMissing($lastPath);
    }

    public function test_advisor_photo_rejects_non_image_and_oversized_file(): void
    {
        $this->actingAs($this->admin)
            ->post(route('investments.advisors.store'), [...$this->payload(), 'photo' => UploadedFile::fake()->create('fake.jpg', 10, 'text/plain')])
            ->assertSessionHasErrors('photo');
        $this->post(route('investments.advisors.store'), [...$this->payload(), 'photo' => UploadedFile::fake()->image('large.png')->size(3073)])
            ->assertSessionHasErrors('photo');
        $this->assertSame(0, InvestmentAdvisor::count());
    }

    public function test_webp_advisor_photo_is_accepted_when_gd_supports_it(): void
    {
        if (! function_exists('imagewebp')) {
            $this->markTestSkipped('La instalación de GD no incluye soporte WEBP.');
        }

        $image = imagecreatetruecolor(120, 120);
        ob_start();
        imagewebp($image);
        $contents = ob_get_clean();
        imagedestroy($image);

        $this->actingAs($this->admin)->post(route('investments.advisors.store'), [
            ...$this->payload(),
            'photo' => UploadedFile::fake()->createWithContent('advisor.webp', $contents),
        ])->assertRedirect();

        $advisor = InvestmentAdvisor::firstOrFail();
        $this->assertNotNull($advisor->photo_path);
        Storage::disk('public')->assertExists($advisor->photo_path);
    }

    public function test_advisor_without_photo_uses_initials_fallback(): void
    {
        $advisor = $this->createAdvisor();
        $this->assertNull($advisor->photoUrl());
        $this->assertSame('AU', $advisor->initials());
        $this->get(route('investments.advisors.public', $advisor->public_token))->assertOk()->assertSee('AU');
    }

    private function createAdvisor(): InvestmentAdvisor
    {
        $this->actingAs($this->admin)
            ->post(route('investments.advisors.store'), $this->payload())
            ->assertRedirect();

        return InvestmentAdvisor::query()->firstOrFail();
    }

    private function payload(string $name = 'Asesor Uno', string $phone = '70001000'): array
    {
        return [
            'full_name' => $name,
            'phone' => $phone,
            'email' => 'advisor@example.test',
            'user_id' => null,
            'is_active' => true,
        ];
    }
}
