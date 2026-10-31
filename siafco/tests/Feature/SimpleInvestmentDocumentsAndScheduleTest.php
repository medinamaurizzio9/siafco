<?php

namespace Tests\Feature;

use App\Models\RolePermissionOverride;
use App\Models\SimpleInvestmentContract;
use App\Models\SimpleInvestmentDocumentVersion;
use App\Models\SimpleInvestmentScheduleNotice;
use App\Models\SimpleInvestmentSetting;
use App\Models\User;
use App\Services\SimpleInvestmentContractService;
use App\Services\SimpleInvestmentDocumentService;
use App\Services\SimpleInvestmentInvestorService;
use App\Services\SimpleInvestmentNoticeService;
use App\Services\SimpleInvestmentScheduleService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class SimpleInvestmentDocumentsAndScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_rrhh_uploads_private_pdf_with_hash_and_supervisor_verifies_it(): void
    {
        Storage::fake('local');
        $supervisor = $this->user('supervisor_inversiones');
        $rrhh = $this->user('rrhh');
        RolePermissionOverride::create([
            'role' => 'rrhh',
            'permissions' => ['rrhh.dashboard.view', 'simple_investments.contracts.view', 'simple_investments.documents.view', 'simple_investments.documents.upload'],
            'updated_by' => $supervisor->id,
        ]);
        $contract = $this->contract($supervisor);
        $pdf = $this->pdf('contrato-firmado.pdf');
        $expectedHash = hash_file('sha256', $pdf->getRealPath());

        $this->actingAs($rrhh)->post(route('simple-investments.contracts.documents.store', $contract), ['document' => $pdf])->assertRedirect();
        $document = SimpleInvestmentDocumentVersion::firstOrFail();
        Storage::disk('local')->assertExists($document->path);
        $this->assertSame($expectedHash, $document->sha256);
        $this->assertSame('application/pdf', $document->mime);
        $this->assertSame(SimpleInvestmentDocumentVersion::UPLOADED, $document->status);
        $this->assertSame(SimpleInvestmentContract::DRAFT, $contract->fresh()->status);

        $this->actingAs($supervisor)->post(route('simple-investments.contracts.documents.verify', [$contract, $document]))->assertRedirect();
        $this->assertSame(SimpleInvestmentDocumentVersion::VERIFIED, $document->fresh()->status);
        $this->assertSame($supervisor->id, $document->fresh()->reviewed_by);
        $this->assertSame(SimpleInvestmentContract::DRAFT, $contract->fresh()->status);
    }

    public function test_image_replacement_preserves_every_private_version_and_history(): void
    {
        Storage::fake('local');
        $supervisor = $this->user('supervisor_inversiones');
        $contract = $this->contract($supervisor);

        $this->actingAs($supervisor)->post(route('simple-investments.contracts.documents.store', $contract), ['document' => UploadedFile::fake()->image('firma-1.jpg')])->assertRedirect();
        $first = SimpleInvestmentDocumentVersion::firstOrFail();
        $this->actingAs($supervisor)->post(route('simple-investments.contracts.documents.observe', [$contract, $first]), ['review_notes' => 'La imagen requiere mayor nitidez.'])->assertRedirect();
        $this->actingAs($supervisor)->post(route('simple-investments.contracts.documents.store', $contract), ['document' => UploadedFile::fake()->image('firma-2.png')])->assertRedirect();

        $versions = $contract->documentVersions()->orderBy('version')->get();
        $this->assertSame([1, 2], $versions->pluck('version')->all());
        $this->assertSame(SimpleInvestmentDocumentVersion::OBSERVED, $versions[0]->status);
        $this->assertSame(SimpleInvestmentDocumentVersion::UPLOADED, $versions[1]->status);
        Storage::disk('local')->assertExists($versions[0]->path);
        Storage::disk('local')->assertExists($versions[1]->path);
        $this->actingAs($supervisor)->get(route('simple-investments.contracts.show', $contract))->assertOk()->assertSee('Historial de versiones')->assertSee('v1')->assertSee('v2');
    }

    public function test_invalid_files_and_storage_failure_leave_no_document_record(): void
    {
        Storage::fake('local');
        $supervisor = $this->user('supervisor_inversiones');
        $contract = $this->contract($supervisor);
        $this->actingAs($supervisor)->post(route('simple-investments.contracts.documents.store', $contract), ['document' => UploadedFile::fake()->createWithContent('malware.svg', '<svg><script>alert(1)</script></svg>')])->assertSessionHasErrors('document');
        $this->assertDatabaseCount('simple_investment_document_versions', 0);

        config()->set('simple_investments.document_max_kb', 1);
        $this->actingAs($supervisor)->post(route('simple-investments.contracts.documents.store', $contract), ['document' => UploadedFile::fake()->createWithContent('grande.pdf', "%PDF-1.4\n".str_repeat('X', 2048))])->assertSessionHasErrors('document');
        $this->assertDatabaseCount('simple_investment_document_versions', 0);
        config()->set('simple_investments.document_max_kb', 10240);

        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('put')->once()->andReturnFalse();
        Storage::shouldReceive('disk')->once()->with('local')->andReturn($disk);
        try {
            app(SimpleInvestmentDocumentService::class)->upload($contract, $this->pdf('fallo.pdf'), $supervisor);
            $this->fail('El fallo de almacenamiento debía impedir el registro.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('document', $exception->errors());
        }
        $this->assertDatabaseCount('simple_investment_document_versions', 0);
    }

    public function test_webp_document_is_accepted_when_gd_supports_it(): void
    {
        if (! function_exists('imagewebp')) {
            $this->markTestSkipped('GD no dispone de soporte WEBP.');
        }

        Storage::fake('local');
        $supervisor = $this->user('supervisor_inversiones');
        $contract = $this->contract($supervisor);
        $image = imagecreatetruecolor(20, 20);
        $path = tempnam(sys_get_temp_dir(), 'siafco-webp-');
        imagewebp($image, $path);
        imagedestroy($image);

        $file = new UploadedFile($path, 'contrato.webp', 'image/webp', null, true);
        $this->actingAs($supervisor)->post(route('simple-investments.contracts.documents.store', $contract), ['document' => $file])->assertRedirect();
        $this->assertSame('image/webp', SimpleInvestmentDocumentVersion::firstOrFail()->mime);
        @unlink($path);
    }

    public function test_document_access_is_authorized_and_scoped_to_its_contract(): void
    {
        Storage::fake('local');
        $supervisor = $this->user('supervisor_inversiones');
        $contract = $this->contract($supervisor);
        $other = $this->contract($supervisor, '998878');
        $document = app(SimpleInvestmentDocumentService::class)->upload($contract, $this->pdf(), $supervisor);

        $this->actingAs($supervisor)->get(route('simple-investments.contracts.documents.view', [$contract, $document]))->assertOk();
        $this->actingAs($supervisor)->get(route('simple-investments.contracts.documents.download', [$contract, $document]))->assertDownload($document->original_name);
        $this->actingAs($supervisor)->get(route('simple-investments.contracts.documents.view', [$other, $document]))->assertNotFound();
        $this->actingAs($this->user('consulta'))->get(route('simple-investments.contracts.documents.download', [$contract, $document]))->assertForbidden();
    }

    public function test_signing_snapshots_schedule_and_configuration_changes_do_not_recalculate_it(): void
    {
        $supervisor = $this->user('supervisor_inversiones');
        $contract = $this->contract($supervisor);
        $service = app(SimpleInvestmentContractService::class);
        $service->markPending($contract);
        $service->sign($contract->fresh(), $supervisor, '2026-12-20');
        $signed = $contract->fresh();
        $this->assertSame(40, $signed->first_schedule_days_snapshot);
        $this->assertSame(30, $signed->schedule_interval_days_snapshot);
        $this->assertSame('America/La_Paz', $signed->schedule_timezone_snapshot);

        SimpleInvestmentSetting::current()->update(['first_schedule_days' => 10, 'schedule_interval_days' => 15]);
        $dates = app(SimpleInvestmentScheduleService::class)->projections($signed, CarbonImmutable::parse('2026-12-20', 'America/La_Paz'));
        $this->assertSame('2027-01-29', $dates[0]['date']->toDateString());
        $this->assertSame('2027-02-28', $dates[1]['date']->toDateString());
    }

    public function test_schedule_handles_leap_year_and_local_timezone_boundary(): void
    {
        $supervisor = $this->user('supervisor_inversiones');
        $contract = $this->signedContract($supervisor, '2028-01-20');
        $service = app(SimpleInvestmentScheduleService::class);
        $beforeLocalMidnight = CarbonImmutable::parse('2028-02-28 23:30:00', 'America/La_Paz');
        $afterUtcMidnight = CarbonImmutable::parse('2028-02-29 03:30:00', 'UTC');

        $first = $service->projections($contract, $beforeLocalMidnight)->first();
        $sameInstant = $service->projections($contract, $afterUtcMidnight)->first();
        $this->assertSame('2028-02-29', $first['date']->toDateString());
        $this->assertSame(1, $first['days_remaining']);
        $this->assertSame(1, $sameInstant['days_remaining']);
    }

    public function test_notices_for_seven_three_due_and_past_are_idempotent_and_never_claim_delivery(): void
    {
        $supervisor = $this->user('supervisor_inversiones');
        $contract = $this->signedContract($supervisor, '2026-10-01');
        $service = app(SimpleInvestmentNoticeService::class);
        $timezone = 'America/La_Paz';

        $this->assertSame(1, $service->prepare(CarbonImmutable::parse('2026-11-03', $timezone)));
        $this->assertSame(0, $service->prepare(CarbonImmutable::parse('2026-11-03', $timezone)));
        $this->assertSame(1, $service->prepare(CarbonImmutable::parse('2026-11-07', $timezone)));
        $this->assertSame(1, $service->prepare(CarbonImmutable::parse('2026-11-10', $timezone)));
        $this->assertSame(1, $service->prepare(CarbonImmutable::parse('2026-11-11', $timezone)));
        $this->assertSame(['advance_3', 'advance_7', 'due', 'past'], SimpleInvestmentScheduleNotice::orderBy('notice_kind')->pluck('notice_kind')->all());
        $this->assertSame(4, SimpleInvestmentScheduleNotice::where('contract_id', $contract->id)->count());
        $this->assertSame(0, SimpleInvestmentScheduleNotice::whereNotNull('sent_at')->count());
        $this->assertSame(0, SimpleInvestmentScheduleNotice::whereNotNull('delivery_confirmed_at')->count());
    }

    public function test_schedule_and_notices_have_no_financial_result_fields(): void
    {
        $this->assertFalse(Schema::hasColumn('simple_investment_schedule_notices', 'amount'));
        $this->assertFalse(Schema::hasColumn('simple_investment_schedule_notices', 'yield'));
        $this->assertFalse(Schema::hasColumn('simple_investment_schedule_notices', 'payment_id'));
    }

    private function contract(User $actor, string $ci = '998877'): SimpleInvestmentContract
    {
        $investor = app(SimpleInvestmentInvestorService::class)->create([
            'full_name' => 'Inversionista '.$ci, 'ci' => $ci, 'ci_complement' => null,
            'expedition_department' => 'LP', 'phone' => '70000000', 'email' => null,
            'address' => 'La Paz', 'registered_on' => '2026-10-30', 'is_active' => true, 'notes' => null,
        ], $actor);

        return app(SimpleInvestmentContractService::class)->create(['investor_id' => $investor->id, 'quantity' => 1, 'registered_on' => '2026-10-30'], $actor);
    }

    private function signedContract(User $actor, string $signedOn): SimpleInvestmentContract
    {
        $contract = $this->contract($actor, str_replace('-', '', $signedOn));
        $service = app(SimpleInvestmentContractService::class);
        $service->markPending($contract);

        return $service->sign($contract->fresh(), $actor, $signedOn)->fresh(['scheduleNotices']);
    }

    private function user(string $role): User
    {
        return User::create(['name' => $role, 'email' => $role.uniqid().'@test.local', 'role' => $role, 'user_type' => 'internal', 'is_active' => true, 'password' => Hash::make('secret')]);
    }

    private function pdf(string $name = 'contrato.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n%%EOF");
    }
}
