<?php

namespace App\Http\Controllers;

use App\Models\InstitutionalSetting;
use App\Services\QrCodeService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class PublicAffiliationQrController extends Controller
{
    private function path(): string
    {
        $path = 'institutional/public-affiliation-qr.png';
        app(QrCodeService::class)->png(config('siafco.public_affiliation_url'), $path, 900, [11, 31, 58]);
        return $path;
    }

    public function show()
    {
        return view('public-affiliation.admin.qr', [
            'setting' => InstitutionalSetting::current(),
            'qrUrl' => Storage::disk('public')->url($this->path()),
        ]);
    }

    public function png()
    {
        return Storage::disk('public')->download($this->path(), 'qr-afiliacion-siafco.png');
    }

    public function pdf()
    {
        $path = storage_path('app/public/'.$this->path());
        return Pdf::loadView('public-affiliation.admin.qr-pdf', [
            'setting' => InstitutionalSetting::current(),
            'qrData' => 'data:image/png;base64,'.base64_encode(file_get_contents($path)),
        ])->download('qr-afiliacion-siafco.pdf');
    }
}
