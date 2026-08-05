<?php

namespace App\Http\Controllers;

use App\Models\InstitutionalSetting;
use App\Services\PublicAffiliationUrlService;
use App\Services\QrCodeService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class PublicAffiliationQrController extends Controller
{
    public function __construct(
        private QrCodeService $qrCodeService,
        private PublicAffiliationUrlService $publicAffiliationUrlService,
    ) {
    }

    private function path(string $publicAffiliationUrl): string
    {
        $path = 'institutional/public-affiliation-qr-'.substr(hash('sha256', $publicAffiliationUrl), 0, 16).'.png';
        $this->qrCodeService->png($publicAffiliationUrl, $path, 900, [11, 31, 58]);

        return $path;
    }

    public function show()
    {
        $publicAffiliationUrl = $this->publicAffiliationUrlService->resolve();

        return view('public-affiliation.admin.qr', [
            'setting' => InstitutionalSetting::current(),
            'publicAffiliationUrl' => $publicAffiliationUrl,
            'qrUrl' => Storage::disk('public')->url($this->path($publicAffiliationUrl)),
        ]);
    }

    public function png()
    {
        $publicAffiliationUrl = $this->publicAffiliationUrlService->resolve();

        return Storage::disk('public')->download(
            $this->path($publicAffiliationUrl),
            'qr-afiliacion-siafco.png',
            ['Cache-Control' => 'no-store, max-age=0']
        );
    }

    public function pdf()
    {
        $publicAffiliationUrl = $this->publicAffiliationUrlService->resolve();
        $path = Storage::disk('public')->path($this->path($publicAffiliationUrl));

        $response = Pdf::loadView('public-affiliation.admin.qr-pdf', [
            'setting' => InstitutionalSetting::current(),
            'publicAffiliationUrl' => $publicAffiliationUrl,
            'qrData' => 'data:image/png;base64,'.base64_encode(file_get_contents($path)),
        ])->download('qr-afiliacion-siafco.pdf');
        $response->headers->set('Cache-Control', 'no-store, max-age=0');

        return $response;
    }
}
