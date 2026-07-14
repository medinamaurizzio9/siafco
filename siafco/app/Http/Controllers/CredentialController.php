<?php

namespace App\Http\Controllers;

use App\Models\Affiliate;
use App\Services\CredentialService;
use Illuminate\Support\Facades\Storage;

class CredentialController extends Controller
{
    public function preview(Affiliate $affiliate, CredentialService $credentialService)
    {
        $this->authorizeCredential($affiliate);
        $credential = $credentialService->generate($affiliate);

        return view('credentials.preview', compact('affiliate', 'credential'));
    }

    public function adminPdf(Affiliate $affiliate, CredentialService $credentialService)
    {
        return $this->download($affiliate, $credentialService, 'pdf');
    }

    public function adminPng(Affiliate $affiliate, CredentialService $credentialService)
    {
        return $this->download($affiliate, $credentialService, 'png');
    }

    public function affiliatePreview(CredentialService $credentialService)
    {
        $affiliate = auth()->user()->affiliate;
        abort_if(! $affiliate, 404);

        return $this->preview($affiliate, $credentialService);
    }

    public function affiliatePdf(CredentialService $credentialService)
    {
        $affiliate = auth()->user()->affiliate;
        abort_if(! $affiliate, 404);

        return $this->download($affiliate, $credentialService, 'pdf');
    }

    public function affiliatePng(CredentialService $credentialService)
    {
        $affiliate = auth()->user()->affiliate;
        abort_if(! $affiliate, 404);

        return $this->download($affiliate, $credentialService, 'png');
    }

    private function download(Affiliate $affiliate, CredentialService $credentialService, string $format)
    {
        $this->authorizeCredential($affiliate);
        $credential = $credentialService->generate($affiliate);
        $path = $format === 'png' ? $credential->png_path : $credential->pdf_path;

        return Storage::disk('public')->download($path, "credencial-{$affiliate->registration_number}.{$format}");
    }

    private function authorizeCredential(Affiliate $affiliate): void
    {
        abort_if(! auth()->user()->hasRole(['administrador', 'secretaria', 'afiliado']), 403);
        abort_if($affiliate->status !== 'activo', 403, 'Debe confirmar su pago para habilitar su credencial digital.');
        abort_if(auth()->user()->role === 'afiliado' && auth()->user()->id !== $affiliate->user_id, 403);
    }
}
