<?php

namespace App\Services;

use App\Models\Affiliate;
use App\Models\DigitalCredential;
use App\Models\InstitutionalSetting;
use App\Support\AffiliationStatusPresenter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class CredentialService
{
    public function __construct(
        private QrCodeService $qrCodeService,
        private CredentialExportCapabilities $capabilities
    ) {
    }

    public function generate(Affiliate $affiliate): DigitalCredential
    {
        $affiliate->load('sector');
        $institution = InstitutionalSetting::current();
        $existingCredential = $affiliate->credential;

        if ($existingCredential && ! $this->shouldRegenerate($existingCredential, $affiliate, $institution)) {
            return $existingCredential;
        }

        $issuedAt = $existingCredential?->created_at ?? now();
        $qrPath = $this->qrCodeService->png(
            route('verify.show', $affiliate->verification_token),
            "credentials/qr/{$affiliate->registration_number}.png",
            360,
            [0, 0, 0]
        );
        $pdfPath = "credentials/pdf/{$affiliate->registration_number}.pdf";
        $pngPath = "credentials/png/{$affiliate->registration_number}.png";
        $credentialData = $this->presentationData($affiliate, $existingCredential, $issuedAt);
        $sources = $this->exportImageSources(
            $affiliate,
            $institution,
            storage_path('app/public/'.$qrPath)
        );

        $this->generatePdf($affiliate, $institution, $credentialData, $sources, $pdfPath);

        $generatedPngPath = null;
        if ($this->capabilities->canExportPng()) {
            $generatedPngPath = $this->generatePng(
                $affiliate,
                $institution,
                $credentialData,
                $sources,
                $pngPath
            ) ? $pngPath : null;
        }

        return DigitalCredential::updateOrCreate(
            ['affiliate_id' => $affiliate->id],
            [
                'qr_path' => $qrPath,
                'pdf_path' => $pdfPath,
                'png_path' => $generatedPngPath,
                'generated_at' => now(),
            ]
        );
    }

    public function presentationData(Affiliate $affiliate, ?DigitalCredential $credential = null, $issuedAt = null): array
    {
        $affiliate->loadMissing('sector');
        $institution = InstitutionalSetting::current();
        $date = $issuedAt ?? $credential?->created_at ?? $affiliate->created_at;

        return [
            'full_name' => mb_strtoupper($affiliate->full_name),
            'affiliate_number' => $affiliate->registration_number,
            'registration_number' => $affiliate->registration_number ?? 'NO REGISTRADO',
            'identity_document' => mb_strtoupper($affiliate->ci),
            'sector' => mb_strtoupper($affiliate->sector?->name ?? 'NO REGISTRADO'),
            'regional' => mb_strtoupper($affiliate->regional ?: 'NO REGISTRADO'),
            'institution' => mb_strtoupper($affiliate->institution ?: $institution->institution_name ?: 'NO REGISTRADO'),
            'issued_at' => $date?->timezone(config('app.timezone'))->format('d/m/Y') ?? 'NO REGISTRADA',
            'version' => config('siafco.credential_version', '2026.1'),
            'institutional_website' => config('siafco.institutional_website', 'www.cooperativatierrabendita.com'),
            'status_label' => mb_strtoupper(AffiliationStatusPresenter::label($affiliate->status)),
        ];
    }

    public function exportImageSources(Affiliate $affiliate, InstitutionalSetting $institution, string $qrAbsolutePath): array
    {
        return [
            'logoSrc' => $this->dataUri($institution->logoAbsolutePath()),
            'photoSrc' => $this->dataUri(
                $affiliate->photo_path ? storage_path('app/public/'.$affiliate->photo_path) : ''
            ),
            'qrSrc' => $this->dataUri($qrAbsolutePath),
        ];
    }

    private function generatePdf(
        Affiliate $affiliate,
        InstitutionalSetting $institution,
        array $credentialData,
        array $sources,
        string $pdfPath
    ): void {
        abort_unless($this->capabilities->canExportPdf(), 503, 'La descarga PDF no está disponible en este servidor.');

        $pdf = Pdf::loadView('credentials.pdf', array_merge([
            'affiliate' => $affiliate,
            'credentialData' => $credentialData,
            'institution' => $institution,
        ], $sources), [], 'UTF-8')
            ->setPaper([0, 0, 242.65, 153.01]);

        Storage::disk('public')->put($pdfPath, $pdf->output());
    }

    private function generatePng(
        Affiliate $affiliate,
        InstitutionalSetting $institution,
        array $credentialData,
        array $sources,
        string $pngPath
    ): bool {
        if ($this->capabilities->chromeBinary()) {
            return $this->generatePngWithBrowser($affiliate, $institution, $credentialData, $sources, $pngPath);
        }

        return false;
    }

    private function generatePngWithBrowser(
        Affiliate $affiliate,
        InstitutionalSetting $institution,
        array $credentialData,
        array $sources,
        string $pngPath
    ): bool {
        $temporaryDirectory = storage_path('framework/cache/credential-export-'.bin2hex(random_bytes(6)));
        if (! mkdir($temporaryDirectory, 0775, true) && ! is_dir($temporaryDirectory)) {
            return false;
        }

        $htmlPath = $temporaryDirectory.DIRECTORY_SEPARATOR.'credential.html';
        $imagePath = $temporaryDirectory.DIRECTORY_SEPARATOR.'credential.png';
        $html = view('credentials.export', array_merge([
            'affiliate' => $affiliate,
            'credentialData' => $credentialData,
            'institution' => $institution,
        ], $sources))->render();
        file_put_contents($htmlPath, $html);

        $process = new Process([
            $this->capabilities->chromeBinary(),
            '--headless=new',
            '--disable-gpu',
            '--disable-breakpad',
            '--disable-crash-reporter',
            '--disable-extensions',
            '--disable-background-networking',
            '--hide-scrollbars',
            '--no-sandbox',
            '--no-first-run',
            '--no-default-browser-check',
            '--allow-file-access-from-files',
            '--force-device-scale-factor=1',
            '--window-size=850,540',
            '--user-data-dir='.$temporaryDirectory.DIRECTORY_SEPARATOR.'profile',
            '--screenshot='.$imagePath,
            'file:///'.str_replace('\\', '/', $htmlPath),
        ]);
        $process->setTimeout(15);

        try {
            try {
                $process->run();
            } catch (\Throwable) {
                $process->stop(0);
            }

            $dimensions = is_file($imagePath) ? getimagesize($imagePath) : false;
            if (! $dimensions || [$dimensions[0], $dimensions[1]] !== [850, 540]) {
                return false;
            }

            Storage::disk('public')->put($pngPath, file_get_contents($imagePath));

            return true;
        } finally {
            $this->removeDirectory($temporaryDirectory);
        }
    }

    private function shouldRegenerate(DigitalCredential $credential, Affiliate $affiliate, InstitutionalSetting $institution): bool
    {
        if (! $credential->pdf_path || ! $credential->qr_path) {
            return true;
        }

        if (
            ! Storage::disk('public')->exists($credential->pdf_path)
            || ! Storage::disk('public')->exists($credential->qr_path)
        ) {
            return true;
        }

        if (
            $this->capabilities->canExportPng()
            && (! $credential->png_path || ! Storage::disk('public')->exists($credential->png_path))
        ) {
            return true;
        }

        if (! $credential->generated_at) {
            return true;
        }

        return $credential->generated_at->timestamp < filemtime(__FILE__)
            || $credential->generated_at->lessThan($affiliate->updated_at)
            || $credential->generated_at->lessThan($institution->updated_at);
    }

    private function dataUri(string $path): ?string
    {
        if (! is_file($path)) {
            return null;
        }

        $mime = mime_content_type($path) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode(file_get_contents($path));
    }

    private function removeDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        for ($attempt = 0; $attempt < 5 && is_dir($directory); $attempt++) {
            if ($attempt > 0) {
                usleep(100000);
            }

            $items = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($items as $item) {
                $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
            }

            @rmdir($directory);
        }
    }
}
