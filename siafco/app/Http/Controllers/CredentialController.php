<?php

namespace App\Http\Controllers;

use App\Models\Affiliate;
use App\Models\InstitutionalSetting;
use App\Services\CredentialService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class CredentialController extends Controller
{
    public function preview(Affiliate $affiliate, CredentialService $credentialService)
    {
        Gate::authorize('viewCredential', $affiliate);
        $credential = $credentialService->generate($affiliate);
        $credentialData = $credentialService->presentationData($affiliate, $credential);
        $institution = InstitutionalSetting::current();

        return view('credentials.preview', compact('affiliate', 'credential', 'credentialData', 'institution'));
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

        Gate::authorize('downloadCredential', $affiliate);

        return $this->download($affiliate, $credentialService, 'pdf');
    }

    public function affiliatePng(CredentialService $credentialService)
    {
        $affiliate = auth()->user()->affiliate;
        abort_if(! $affiliate, 404);

        Gate::authorize('downloadCredential', $affiliate);

        return $this->download($affiliate, $credentialService, 'png');
    }

    public function print(Affiliate $affiliate, CredentialService $credentialService)
    {
        Gate::authorize('printCredential', $affiliate);
        $credential = $credentialService->generate($affiliate);
        $credentialData = $credentialService->presentationData($affiliate, $credential);
        $institution = InstitutionalSetting::current();
        $printMode = true;

        return view('credentials.preview', compact('affiliate', 'credential', 'credentialData', 'institution', 'printMode'));
    }

    private function download(Affiliate $affiliate, CredentialService $credentialService, string $format)
    {
        Gate::authorize('downloadCredential', $affiliate);
        $credential = $credentialService->generate($affiliate);
        $path = $format === 'png' ? $credential->png_path : $credential->pdf_path;

        return Storage::disk('public')->download($path, "credencial-{$affiliate->registration_number}.{$format}");
    }

}
