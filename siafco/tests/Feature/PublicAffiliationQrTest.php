<?php

namespace Tests\Feature;

use App\Models\InstitutionalSetting;
use App\Models\User;
use App\Services\PublicAffiliationUrlService;
use App\Services\QrCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class PublicAffiliationQrTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        InstitutionalSetting::clearCurrentCache();
    }

    public function test_public_affiliation_route_is_named_and_has_expected_uri(): void
    {
        $this->assertSame('http://127.0.0.1:8000/afiliacion', app(PublicAffiliationUrlService::class)->resolve());
        $this->get(route('public-affiliation.index'))->assertOk();
    }

    public function test_public_affiliation_url_uses_configured_local_app_url(): void
    {
        config(['app.url' => 'http://127.0.0.1:8000']);

        $this->assertSame('http://127.0.0.1:8000/afiliacion', app(PublicAffiliationUrlService::class)->resolve());
    }

    public function test_public_affiliation_url_uses_configured_production_app_url(): void
    {
        config(['app.url' => 'https://siafco.viankagold.com']);

        $this->assertSame('https://siafco.viankagold.com/afiliacion', app(PublicAffiliationUrlService::class)->resolve());
    }

    public function test_public_affiliation_url_uses_configured_temporary_aws_app_url(): void
    {
        config(['app.url' => 'https://52.203.59.255.nip.io']);

        $this->assertSame('https://52.203.59.255.nip.io/afiliacion', app(PublicAffiliationUrlService::class)->resolve());
    }

    public function test_qr_view_shows_same_url_used_to_generate_png(): void
    {
        Storage::fake('public');
        config(['app.url' => 'http://127.0.0.1:8000']);
        $expectedUrl = 'http://127.0.0.1:8000/afiliacion';
        $this->fakeQrGeneratorFor($expectedUrl);

        $this->actingAs($this->user())->get(route('public-affiliation.qr.show'))
            ->assertOk()
            ->assertSee($expectedUrl, false)
            ->assertDontSee('siafco.viankagold.com/afiliacion');
    }

    public function test_png_download_uses_dynamic_public_affiliation_url(): void
    {
        Storage::fake('public');
        config(['app.url' => 'https://52.203.59.255.nip.io']);
        $expectedUrl = 'https://52.203.59.255.nip.io/afiliacion';
        $this->fakeQrGeneratorFor($expectedUrl);

        $response = $this->actingAs($this->user())->get(route('public-affiliation.qr.png'));

        $response->assertOk();
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));

        Storage::disk('public')->assertExists($this->expectedQrPath($expectedUrl));
    }

    public function test_pdf_download_uses_dynamic_public_affiliation_url(): void
    {
        Storage::fake('public');
        config(['app.url' => 'https://siafco.viankagold.com']);
        $expectedUrl = 'https://siafco.viankagold.com/afiliacion';
        $this->fakeQrGeneratorFor($expectedUrl);

        $response = $this->actingAs($this->user())->get(route('public-affiliation.qr.pdf'));

        $response->assertOk();
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));

        Storage::disk('public')->assertExists($this->expectedQrPath($expectedUrl));
    }

    public function test_changing_app_url_generates_a_distinct_qr_file(): void
    {
        Storage::fake('public');
        $this->fakeQrGenerator();
        $user = $this->user();

        config(['app.url' => 'http://127.0.0.1:8000']);
        $this->actingAs($user)->get(route('public-affiliation.qr.show'))->assertOk();

        config(['app.url' => 'https://52.203.59.255.nip.io']);
        $this->actingAs($user)->get(route('public-affiliation.qr.show'))->assertOk();

        Storage::disk('public')->assertExists($this->expectedQrPath('http://127.0.0.1:8000/afiliacion'));
        Storage::disk('public')->assertExists($this->expectedQrPath('https://52.203.59.255.nip.io/afiliacion'));
        $this->assertNotSame(
            $this->expectedQrPath('http://127.0.0.1:8000/afiliacion'),
            $this->expectedQrPath('https://52.203.59.255.nip.io/afiliacion')
        );
    }

    public function test_malicious_host_header_does_not_change_public_affiliation_qr_url(): void
    {
        Storage::fake('public');
        config(['app.url' => 'https://siafco.viankagold.com']);
        $expectedUrl = 'https://siafco.viankagold.com/afiliacion';
        $this->fakeQrGeneratorFor($expectedUrl);

        $this->actingAs($this->user())
            ->withServerVariables(['HTTP_HOST' => 'evil.test'])
            ->get(route('public-affiliation.qr.show'))
            ->assertOk()
            ->assertSee($expectedUrl, false)
            ->assertDontSee('evil.test');
    }

    public function test_operational_code_has_no_fixed_public_affiliation_domains(): void
    {
        $roots = [
            app_path(),
            config_path(),
            resource_path('views'),
            base_path('routes'),
        ];

        $contents = collect($roots)
            ->flatMap(fn (string $root) => File::allFiles($root))
            ->map(fn ($file) => $file->getContents())
            ->implode("\n");

        foreach ([
            'siafco.viankagold.com/afiliacion',
            '127.0.0.1:8000/afiliacion',
            'nip.io/afiliacion',
        ] as $fixedDomain) {
            $this->assertStringNotContainsString($fixedDomain, $contents);
        }
    }

    private function fakeQrGeneratorFor(string $expectedUrl): void
    {
        $this->fakeQrGenerator($expectedUrl);
    }

    private function fakeQrGenerator(?string $expectedUrl = null): void
    {
        $this->mock(QrCodeService::class, function ($mock) use ($expectedUrl) {
            $mock->shouldReceive('png')
                ->with(
                    Mockery::on(fn (string $url) => $expectedUrl === null || $url === $expectedUrl),
                    Mockery::type('string'),
                    900,
                    [11, 31, 58]
                )
                ->andReturnUsing(function (string $url, string $path): string {
                    Storage::disk('public')->put($path, base64_decode($this->tinyPng(), true));

                    return $path;
                });
        });
    }

    private function expectedQrPath(string $url): string
    {
        return 'institutional/public-affiliation-qr-'.substr(hash('sha256', $url), 0, 16).'.png';
    }

    private function tinyPng(): string
    {
        return 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
    }

    private function user(string $role = 'secretaria'): User
    {
        return User::create([
            'name' => 'Secretaria',
            'email' => fake()->unique()->safeEmail(),
            'role' => $role,
            'password' => Hash::make('Password1'),
        ]);
    }
}
