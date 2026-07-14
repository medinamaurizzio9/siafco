<?php

namespace App\Http\Controllers;

use App\Models\InstitutionalSetting;
use App\Services\QrCodeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class InstitutionalQrController extends Controller
{
    public function show()
    {
        $setting = InstitutionalSetting::current();
        if (! Storage::disk('public')->exists($setting->paymentQrPath())) {
            app(QrCodeService::class)->png('SIAFCO TIERRA BENDITA - PAGO AFILIACION/CREDENCIAL', $setting->paymentQrPath());
        }

        return view('institutional-qr.show', compact('setting'));
    }

    public function update(Request $request)
    {
        $request->validate(['qr' => ['required', 'image', 'max:2048']]);
        $path = $request->file('qr')->storeAs('institutional', 'payment-qr.png', 'public');
        InstitutionalSetting::current()->update(['payment_qr_path' => $path]);

        return back()->with('status', 'QR institucional actualizado.');
    }
}
