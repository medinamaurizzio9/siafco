<?php

namespace Tests\Feature;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

class ResponsiveWebPresentationTest extends TestCase
{
    public function test_global_confirmation_modal_is_centered_and_accessible(): void
    {
        $component = file_get_contents(resource_path('views/components/ui/confirm-modal.blade.php'));
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString('role="dialog"', $component);
        $this->assertStringContainsString('aria-modal="true"', $component);
        $this->assertStringContainsString('aria-labelledby="global-confirm-title"', $component);
        $this->assertMatchesRegularExpression('/\.confirm-modal\s*\{[^}]*position:\s*fixed;/s', $css);
        $this->assertMatchesRegularExpression('/\.confirm-modal\s*\{[^}]*inset:\s*0;/s', $css);
        $this->assertMatchesRegularExpression('/\.confirm-modal\s*\{[^}]*margin:\s*auto;/s', $css);
        $this->assertStringContainsString('overflow-y: auto', $css);
    }

    public function test_main_layout_keeps_mobile_drawer_and_unconditional_logout_controls(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));

        $this->assertStringContainsString('id="mobile-sidebar"', $layout);
        $this->assertStringContainsString('data-sidebar-open', $layout);
        $this->assertStringContainsString('aria-controls="mobile-sidebar"', $layout);
        $this->assertStringContainsString('data-sidebar-backdrop', $layout);
        $this->assertStringContainsString('data-sidebar-link', $layout);
        $this->assertStringContainsString('data-confirm-title="Cerrar sesión"', $layout);
        $this->assertStringContainsString('method="post" action="{{ route(\'logout\') }}"', $layout);
    }

    public function test_operational_blade_tables_have_horizontal_overflow_protection(): void
    {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views')));
        $unprotected = [];

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $path = $file->getPathname();
            if (str_contains($path, DIRECTORY_SEPARATOR.'pdf'.DIRECTORY_SEPARATOR)
                || str_ends_with($path, 'pdf.blade.php')
                || str_ends_with($path, 'receipt.blade.php')) {
                continue;
            }

            $contents = file_get_contents($path);
            if (str_contains($contents, '<table') && ! str_contains($contents, 'overflow-x-auto')) {
                $unprotected[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $path);
            }
        }

        $this->assertSame([], $unprotected, 'Tables without responsive overflow: '.implode(', ', $unprotected));
    }

    public function test_critical_forms_keep_responsive_grids_and_global_confirmation(): void
    {
        $office = file_get_contents(resource_path('views/affiliates/office-form.blade.php'));
        $public = file_get_contents(resource_path('views/public-affiliation/create.blade.php'));

        $this->assertStringContainsString('md:grid-cols-2 xl:grid-cols-3', $office);
        $this->assertStringContainsString('data-confirm-office-affiliation', $office);
        $this->assertStringContainsString('sm:grid-cols-2', $public);
        $this->assertStringContainsString('@media (max-width: 639px)', file_get_contents(resource_path('css/app.css')));
    }
}
