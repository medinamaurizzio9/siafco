<?php

namespace Tests\Feature;

use App\Models\AffiliationPlan;
use App\Models\Affiliate;
use App\Models\InvestmentLot;
use App\Models\InstitutionalSetting;
use App\Models\InvestmentReturnPeriod;
use App\Models\InvestmentSetting;
use App\Models\Investor;
use App\Models\InvestorType;
use App\Models\Person;
use App\Models\Sector;
use App\Models\User;
use App\Services\InvestmentService;
use App\Services\CredentialService;
use App\Services\QrCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InvestmentSubsystemTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.local',
            'role' => 'administrador',
            'password' => Hash::make('secret'),
        ]);

        $this->actingAs($this->admin);

        InvestmentSetting::create([
            'company_name' => 'Tierra Bendita',
            'share_unit_price' => 14000,
            'minimum_shares' => 1,
            'maximum_shares' => 10,
            'monthly_return_percentage' => 5,
            'waiting_months' => 4,
            'contract_years' => 3,
            'reservation_days' => 30,
            'receipt_prefix' => 'REC-INV',
            'next_receipt_number' => 1,
            'alert_days_before_maturity' => 15,
            'active' => true,
        ]);

        foreach (range(1, 10) as $shares) {
            InvestorType::create(['name' => "Inversionista {$shares}", 'shares_quantity' => $shares, 'active' => true, 'order' => $shares]);
        }
    }

    public function test_reuses_existing_affiliate_person_by_ci(): void
    {
        $sector = Sector::create(['name' => 'Rural', 'code' => 'MAG-RUR', 'is_active' => true]);
        $plan = AffiliationPlan::create(['name' => 'Inicial', 'affiliation_fee' => 100, 'credential_fee' => 30, 'is_active' => true]);

        $this->post(route('affiliates.store'), [
            'full_name' => 'Maria Perez',
            'ci' => '123456',
            'email' => 'maria@test.local',
            'sector_id' => $sector->id,
            'affiliation_plan_id' => $plan->id,
        ])->assertRedirect();

        $investor = app(InvestmentService::class)->createInvestor([
            'full_name' => 'Maria Perez',
            'ci' => '123456',
            'email' => 'maria@test.local',
        ], ['status' => 'prospect']);

        $this->assertSame(1, Person::where('ci', '123456')->count());
        $this->assertSame('123456', $investor->person->ci);
    }

    public function test_creates_non_affiliate_investor_and_prevents_duplicate_investor_ci(): void
    {
        $service = app(InvestmentService::class);
        $service->createInvestor(['full_name' => 'Juan Soto', 'ci' => '777'], ['status' => 'prospect']);

        $this->expectException(ValidationException::class);
        $service->createInvestor(['full_name' => 'Juan Soto', 'ci' => '777'], ['status' => 'prospect']);
    }

    public function test_creates_reservation_for_thirty_days_without_returns(): void
    {
        $investor = $this->investor();
        $reservation = app(InvestmentService::class)->createReservation($investor, [
            'shares_quantity' => 1,
            'reservation_date' => '2027-01-10',
            'amount_paid' => 1000,
        ]);

        $this->assertSame('2027-02-09', $reservation->expiration_date->toDateString());
        $this->assertSame(0, InvestmentReturnPeriod::count());
    }

    public function test_approves_paid_lot_and_generates_thirty_six_periods(): void
    {
        $investor = $this->investor();
        $lot = app(InvestmentService::class)->createLot($investor, [
            'purchase_date' => '2027-01-10',
            'shares_quantity' => 4,
            'payment_method' => 'Transferencia',
        ]);

        app(InvestmentService::class)->approveLot($lot);
        $lot->refresh();

        $this->assertSame('2027-05-10', $lot->maturity_date->toDateString());
        $this->assertSame('active_waiting', $lot->status);
        $this->assertSame('56000.00', $lot->invested_capital);
        $this->assertSame(36, $lot->periods()->count());
        $this->assertSame('2800.00', $lot->periods()->first()->base_return_amount);
    }

    public function test_prevents_receipt_before_approval_and_prevents_double_receipt(): void
    {
        $lot = app(InvestmentService::class)->createLot($this->investor(), [
            'purchase_date' => '2027-01-10',
            'shares_quantity' => 1,
            'payment_method' => 'Transferencia',
        ]);
        app(InvestmentService::class)->approveLot($lot);
        $period = $lot->periods()->first();

        $this->expectException(ValidationException::class);
        app(InvestmentService::class)->issueReceipt($period, ['payment_method' => 'Caja']);
    }

    public function test_issues_receipt_after_approval(): void
    {
        $lot = app(InvestmentService::class)->createLot($this->investor(), [
            'purchase_date' => '2027-01-10',
            'shares_quantity' => 1,
            'payment_method' => 'Transferencia',
        ]);
        app(InvestmentService::class)->approveLot($lot);
        $period = $lot->periods()->first();

        app(InvestmentService::class)->preparePeriod($period, ['production_bonus_amount' => 0, 'extra_amount' => 0]);
        app(InvestmentService::class)->approvePeriod($period->refresh());
        $receipt = app(InvestmentService::class)->issueReceipt($period->refresh(), ['payment_method' => 'Caja']);

        $this->assertSame('REC-INV-'.now()->format('Y').'-000001', $receipt->receipt_number);
        $this->assertSame('paid', $period->refresh()->status);
        $this->assertSame(1, InvestmentLot::count());
    }

    public function test_admin_investment_pages_render(): void
    {
        foreach ([
            'investments.dashboard',
            'investments.investors.index',
            'investments.investor-types.index',
            'investments.reservations.index',
            'investments.lots.index',
            'investments.returns.index',
            'investments.receipts.index',
            'investments.settings.edit',
            'investments.reports.index',
        ] as $route) {
            $this->get(route($route))->assertOk();
        }
    }

    public function test_pdf_qr_and_storage_assets_are_generated(): void
    {
        config([
            'siafco.credential_version' => '2026.1-test',
            'siafco.institutional_website' => 'www.cooperativatierrabendita.com',
        ]);

        $sector = Sector::create(['name' => 'Rural', 'code' => 'MAG-RUR', 'current_sequence' => 1, 'is_active' => true]);
        $plan = AffiliationPlan::create(['name' => 'Inicial', 'affiliation_fee' => 100, 'credential_fee' => 30, 'is_active' => true]);
        Storage::disk('public')->put(
            'institutional/test-logo.png',
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl2nWQAAAAASUVORK5CYII=')
        );
        InstitutionalSetting::query()->firstOrNew()->fill([
            'institution_name' => 'Cooperativa Tierra Bendita',
            'logo_path' => 'institutional/test-logo.png',
            'primary_color' => '#0b1f3a',
            'secondary_color' => '#d4af37',
            'payment_qr_path' => 'institutional/payment-qr.png',
        ])->save();
        InstitutionalSetting::clearCurrentCache();

        $affiliateUser = User::create([
            'name' => 'Afiliado Activo',
            'email' => 'activo@test.local',
            'role' => 'afiliado',
            'password' => Hash::make('secret'),
        ]);

        $affiliate = Affiliate::create([
            'user_id' => $affiliateUser->id,
            'sector_id' => $sector->id,
            'affiliation_plan_id' => $plan->id,
            'full_name' => 'Afiliado Activo',
            'ci' => '990011',
            'email' => 'activo@test.local',
            'registration_number' => 'MAG-RUR-000001',
            'status' => 'activo',
            'verification_token' => 'test-token-credential',
        ]);

        $realQrService = new QrCodeService();
        $this->mock(QrCodeService::class)
            ->shouldReceive('png')
            ->once()
            ->with(
                route('verify.show', 'test-token-credential'),
                'credentials/qr/MAG-RUR-000001.png',
                360,
                [0, 0, 0]
            )
            ->andReturnUsing(fn (string $data, string $path, int $size, array $foreground) => $realQrService->png($data, $path, $size, $foreground));

        $credential = app(CredentialService::class)->generate($affiliate);
        $issuedAt = $credential->created_at->timezone(config('app.timezone'))->format('d/m/Y');
        $qrHash = hash_file('sha256', storage_path('app/public/'.$credential->qr_path));

        $this->assertFileExists(storage_path('app/public/'.$credential->qr_path));
        $this->assertFileExists(storage_path('app/public/'.$credential->png_path));
        $this->assertFileExists(storage_path('app/public/'.$credential->pdf_path));
        $pngSize = getimagesize(storage_path('app/public/'.$credential->png_path));
        $this->assertSame([850, 540], array_slice($pngSize, 0, 2));
        $this->get(route('credentials.pdf', $affiliate))->assertOk();
        $this->get(route('credentials.preview', $affiliate))
            ->assertOk()
            ->assertSee('CREDENCIAL DE AFILIADO')
            ->assertSee('AFILIADO ACTIVO')
            ->assertSee('ESCANEA PARA VERIFICAR')
            ->assertSee('FECHA DE EMISIÓN')
            ->assertDontSee('NÚMERO DE REGISTRO')
            ->assertSee('MAG-RUR-000001')
            ->assertSee($issuedAt)
            ->assertSee('2026.1-test')
            ->assertSee('www.cooperativatierrabendita.com')
            ->assertSee('credential-watermark')
            ->assertDontSee('<div class="credential-watermark" aria-hidden="true">SIAFCO', false);

        $regenerated = app(CredentialService::class)->generate($affiliate->refresh());
        $this->assertSame($issuedAt, $regenerated->created_at->timezone(config('app.timezone'))->format('d/m/Y'));
        $this->assertSame($qrHash, hash_file('sha256', storage_path('app/public/'.$regenerated->qr_path)));
        $this->get(route('reports.pdf'))->assertOk();

        $lot = app(InvestmentService::class)->createLot($this->investor(), [
            'purchase_date' => '2027-01-10',
            'shares_quantity' => 1,
            'payment_method' => 'Transferencia',
        ]);
        app(InvestmentService::class)->approveLot($lot);
        $period = $lot->periods()->first();
        app(InvestmentService::class)->preparePeriod($period, ['production_bonus_amount' => 0, 'extra_amount' => 0]);
        app(InvestmentService::class)->approvePeriod($period->refresh());
        $receipt = app(InvestmentService::class)->issueReceipt($period->refresh(), ['payment_method' => 'Caja']);

        $this->get(route('investments.receipts.pdf', $receipt))->assertOk();
    }

    private function investor(): Investor
    {
        return app(InvestmentService::class)->createInvestor([
            'full_name' => 'Pedro Rojas',
            'ci' => fake()->unique()->numerify('#######'),
        ], ['status' => 'prospect']);
    }
}
