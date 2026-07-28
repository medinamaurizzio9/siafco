<?php

namespace Tests\Unit;

use App\Services\CredentialExportCapabilities;
use Tests\TestCase;

class CredentialExportCapabilitiesTest extends TestCase
{
    public function test_shared_hosting_without_browser_or_imagick_keeps_only_pdf_export(): void
    {
        config([
            'siafco.credential_export.chrome_binary' => null,
            'siafco.credential_export.enable_png' => true,
            'siafco.credential_export.allow_gd_fallback' => false,
        ]);

        $capabilities = new class extends CredentialExportCapabilities
        {
            public function chromeBinary(): ?string
            {
                return null;
            }
        };

        $this->assertTrue($capabilities->canExportPdf());
        $this->assertFalse($capabilities->canExportPng());
        $this->assertFalse(config('siafco.credential_export.allow_gd_fallback'));
        $this->assertStringNotContainsString(
            'imagecreatetruecolor',
            file_get_contents(app_path('Services/CredentialService.php'))
        );
        $this->assertStringNotContainsString(
            'renderWithGd',
            file_get_contents(app_path('Services/CredentialService.php'))
        );
        $this->assertStringNotContainsString(
            'Imagick',
            file_get_contents(app_path('Services/CredentialService.php'))
        );
    }
}
