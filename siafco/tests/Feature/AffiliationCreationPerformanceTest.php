<?php

namespace Tests\Feature;

use App\Models\AffiliationPlan;
use App\Models\Sector;
use App\Models\User;
use App\Services\AffiliatePhotoProcessor;
use App\Support\PublicAffiliationCatalogs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class AffiliationCreationPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_creation_flows_keep_a_bounded_query_budget_and_expected_side_effects(): void
    {
        Storage::fake('public');
        $queryCount = 0;
        $queryTime = 0.0;
        DB::listen(static function ($query) use (&$queryCount, &$queryTime): void {
            $queryCount++;
            $queryTime += (float) $query->time;
        });

        [$sector, $plan] = $this->catalog('PERF');
        $metrics = [];

        $metrics['public'] = $this->measure($queryCount, $queryTime, fn () => $this->post(
            route('public-affiliation.store'),
            $this->publicPayload($sector, $plan, 'PUBLIC-1', UploadedFile::fake()->image('public.jpg', 600, 760))
        )->assertRedirect());

        $admin = User::factory()->create(['role' => 'administrador', 'user_type' => 'internal', 'is_active' => true]);
        $metrics['admin'] = $this->measure($queryCount, $queryTime, fn () => $this->actingAs($admin)->post(
            route('affiliates.store'),
            $this->adminPayload($sector, $plan, 'ADMIN-1', UploadedFile::fake()->image('admin.jpg', 600, 760))
        )->assertRedirect());

        $cashier = User::factory()->create(['role' => 'cajero', 'user_type' => 'internal', 'is_active' => true]);
        $metrics['office_cash'] = $this->measure($queryCount, $queryTime, fn () => $this->actingAs($cashier)->post(
            route('affiliates.office.store'),
            $this->officePayload($sector, $plan, 'CASH-1', 'efectivo')
        )->assertRedirect());
        $metrics['office_qr'] = $this->measure($queryCount, $queryTime, fn () => $this->actingAs($cashier)->post(
            route('affiliates.office.store'),
            $this->officePayload($sector, $plan, 'QR-1', 'qr')
        )->assertRedirect());

        fwrite(STDERR, PHP_EOL.'AFFILIATION_PERF '.json_encode($metrics, JSON_UNESCAPED_SLASHES).PHP_EOL);

        $this->assertLessThan(45, $metrics['public']['queries']);
        $this->assertLessThan(45, $metrics['admin']['queries']);
        $this->assertLessThan(70, $metrics['office_cash']['queries']);
        $this->assertLessThan(45, $metrics['office_qr']['queries']);
        $this->assertDatabaseCount('people', 4);
        $this->assertDatabaseCount('users', 6);
        $this->assertDatabaseCount('affiliates', 4);
        $this->assertDatabaseCount('affiliation_payments', 3);
    }

    public function test_photo_processor_handles_small_and_large_inputs_once(): void
    {
        Storage::fake('public');
        $processor = app(AffiliatePhotoProcessor::class);
        $metrics = [];

        foreach (['small' => [600, 760], 'large' => [2400, 3040]] as $name => [$width, $height]) {
            $photo = UploadedFile::fake()->image($name.'.jpg', $width, $height);
            $start = hrtime(true);
            $path = $processor->process($photo, 600, 760);
            $metrics[$name] = round((hrtime(true) - $start) / 1_000_000, 2);
            Storage::disk('public')->assertExists($path);
            $this->assertSame([600, 760], array_slice(getimagesize(Storage::disk('public')->path($path)), 0, 2));
        }

        fwrite(STDERR, PHP_EOL.'AFFILIATION_PHOTO_PERF '.json_encode($metrics).PHP_EOL);
        $this->assertDatabaseCount('affiliates', 0);
    }

    public function test_each_web_creation_flow_processes_an_uploaded_photo_once(): void
    {
        Storage::fake('public');
        $photos = Mockery::mock(AffiliatePhotoProcessor::class);
        $photos->shouldReceive('process')->times(3)->andReturn('affiliates/photos/measured.jpg');
        $this->app->instance(AffiliatePhotoProcessor::class, $photos);
        [$sector, $plan] = $this->catalog('ONCE');

        $this->post(route('public-affiliation.store'), $this->publicPayload(
            $sector,
            $plan,
            'ONCE-PUBLIC',
            UploadedFile::fake()->image('public.jpg')
        ))->assertRedirect();

        $admin = User::factory()->create(['role' => 'administrador', 'user_type' => 'internal', 'is_active' => true]);
        $this->actingAs($admin)->post(route('affiliates.store'), $this->adminPayload(
            $sector,
            $plan,
            'ONCE-ADMIN',
            UploadedFile::fake()->image('admin.jpg')
        ))->assertRedirect();

        $cashier = User::factory()->create(['role' => 'cajero', 'user_type' => 'internal', 'is_active' => true]);
        $office = $this->officePayload($sector, $plan, 'ONCE-OFFICE', 'qr');
        $office['photo'] = UploadedFile::fake()->image('office.jpg');
        $this->actingAs($cashier)->post(route('affiliates.office.store'), $office)->assertRedirect();

        $this->assertDatabaseCount('people', 3);
        $this->assertDatabaseCount('affiliates', 3);
    }

    private function measure(int &$queryCount, float &$queryTime, callable $operation): array
    {
        $queryCount = 0;
        $queryTime = 0.0;
        $start = hrtime(true);
        $operation();

        return [
            'total_ms' => round((hrtime(true) - $start) / 1_000_000, 2),
            'queries' => $queryCount,
            'db_ms' => round($queryTime, 2),
        ];
    }

    private function catalog(string $suffix): array
    {
        $sector = Sector::create(['name' => 'SECTOR '.$suffix, 'code' => $suffix, 'is_active' => true]);
        $plan = AffiliationPlan::create([
            'sector_id' => $sector->id,
            'name' => 'PLAN '.$suffix,
            'type' => 'independiente',
            'affiliation_fee' => 100,
            'credential_fee' => 20,
            'currency' => 'BOB',
            'is_active' => true,
        ]);

        return [$sector, $plan];
    }

    private function commonPayload(Sector $sector, AffiliationPlan $plan, string $suffix): array
    {
        return [
            'full_name' => 'PERSONA '.$suffix,
            'ci' => 'CI-'.$suffix,
            'phone' => '70000001',
            'email' => strtolower($suffix).'@performance.test',
            'address' => 'DIRECCION '.$suffix,
            'sector_id' => $sector->id,
            'affiliation_plan_id' => $plan->id,
            'regional' => PublicAffiliationCatalogs::REGIONALS[0],
            'institution' => null,
            'position' => null,
            'birth_date' => '1990-01-15',
            'marital_status' => PublicAffiliationCatalogs::MARITAL_STATUSES[0],
        ];
    }

    private function publicPayload(Sector $sector, AffiliationPlan $plan, string $suffix, UploadedFile $photo): array
    {
        return $this->commonPayload($sector, $plan, $suffix) + [
            'issued_in' => 'LP',
            'password' => 'Clave-segura-123',
            'password_confirmation' => 'Clave-segura-123',
            'photo' => $photo,
            'terms' => '1',
            'data_processing' => '1',
        ];
    }

    private function adminPayload(Sector $sector, AffiliationPlan $plan, string $suffix, UploadedFile $photo): array
    {
        return $this->commonPayload($sector, $plan, $suffix) + ['photo' => $photo];
    }

    private function officePayload(Sector $sector, AffiliationPlan $plan, string $suffix, string $method): array
    {
        return $this->commonPayload($sector, $plan, $suffix) + [
            'received_amount' => '120.00',
            'paid_at' => now()->format('Y-m-d\TH:i'),
            'payment_method' => $method,
            'reference_number' => $method === 'qr' ? 'TRX-'.$suffix : null,
            'observations' => 'PRUEBA DE RENDIMIENTO',
        ];
    }
}
