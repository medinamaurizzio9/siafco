<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\Process\Process;

class CredentialExportCapabilities
{
    private bool $chromeResolved = false;

    private ?string $resolvedChromeBinary = null;

    public function canExportPdf(): bool
    {
        return class_exists(Pdf::class) && class_exists(\Dompdf\Dompdf::class);
    }

    public function canExportPng(): bool
    {
        return (bool) config('siafco.credential_export.enable_png', true)
            && $this->chromeBinary() !== null;
    }

    public function canPrintCredential(): bool
    {
        return true;
    }

    public function pdfEngine(): string
    {
        return $this->canExportPdf() ? 'Dompdf' : 'No disponible';
    }

    public function pngEngine(): string
    {
        return $this->canExportPng() ? 'Chrome Headless' : 'No disponible';
    }

    public function pngUnavailableReason(): ?string
    {
        return $this->canExportPng()
            ? null
            : 'Este servidor no dispone de Chrome/Chromium funcional o la exportación PNG está deshabilitada.';
    }

    public function chromeBinary(): ?string
    {
        if ($this->chromeResolved) {
            return $this->resolvedChromeBinary;
        }

        $this->chromeResolved = true;
        $configured = config('siafco.credential_export.chrome_binary');
        $candidates = array_filter([
            $configured,
            '/usr/bin/google-chrome',
            '/usr/bin/google-chrome-stable',
            '/usr/bin/chromium',
            '/usr/bin/chromium-browser',
        ]);

        foreach ($candidates as $candidate) {
            if (! is_file($candidate) || ! is_executable($candidate)) {
                continue;
            }

            try {
                $process = new Process([$candidate, '--version']);
                $process->setTimeout(3);
                $process->run();

                if ($process->isSuccessful()) {
                    return $this->resolvedChromeBinary = $candidate;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }
}
