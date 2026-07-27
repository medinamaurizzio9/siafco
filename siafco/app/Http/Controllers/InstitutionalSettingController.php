<?php

namespace App\Http\Controllers;

use App\Models\InstitutionalSetting;
use App\Services\AuditService;
use App\Support\TextNormalizer;
use Illuminate\Http\Request;

class InstitutionalSettingController extends Controller
{
    public function edit()
    {
        return view('institutional-settings.edit', [
            'setting' => InstitutionalSetting::current(),
        ]);
    }

    public function update(Request $request)
    {
        $setting = InstitutionalSetting::current();

        $data = $request->validate([
            'institution_name' => ['required', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'primary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:80'],
            'address' => ['nullable', 'string', 'max:255'],
            'payment_qr' => ['nullable', 'image', 'max:4096'],
            'payment_bank' => ['nullable', 'string', 'max:120'],
            'payment_holder' => ['nullable', 'string', 'max:255'],
            'payment_account' => ['nullable', 'string', 'max:120'],
            'payment_instructions' => ['nullable', 'string', 'max:2000'],
        ]);

        unset($data['logo'], $data['payment_qr']);
        $data = TextNormalizer::fields($data, [
            'institution_name', 'address', 'payment_bank', 'payment_holder', 'payment_instructions',
        ]);

        if ($request->hasFile('logo')) {
            $data['logo_path'] = $request->file('logo')->store('institutional/logo', 'public');
        }

        if ($request->hasFile('payment_qr')) {
            $data['payment_qr_path'] = $request->file('payment_qr')->storeAs('institutional', 'payment-qr.png', 'public');
        }

        $setting->update($data);
        InstitutionalSetting::clearCurrentCache();
        AuditService::record('configuracion_institucional.actualizada', $setting);

        return back()->with('status', 'Configuracion institucional actualizada.');
    }
}
