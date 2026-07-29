<?php

namespace App\Http\Controllers;

use App\Models\InstitutionalSetting;
use App\Services\AuditService;
use App\Services\CredentialExportCapabilities;
use App\Support\TextNormalizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class InstitutionalSettingController extends Controller
{
    public function edit(CredentialExportCapabilities $exportCapabilities)
    {
        return view('institutional-settings.edit', [
            'setting' => InstitutionalSetting::current(),
            'exportCapabilities' => $exportCapabilities,
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
            'login_background' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'login_logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'login_title' => ['required', 'string', 'max:120'],
            'login_institution_name' => ['required', 'string', 'max:180'],
            'login_affiliate_message' => ['nullable', 'string', 'max:800'],
            'login_overlay_opacity' => ['required', 'integer', 'between:20,90'],
            'remove_login_background' => ['nullable', 'boolean'],
            'remove_login_logo' => ['nullable', 'boolean'],
        ]);

        unset($data['logo'], $data['login_background'], $data['login_logo']);
        $data = TextNormalizer::fields($data, [
            'institution_name', 'address',
        ]);

        $newPaths = [];
        $oldPaths = [];

        if ($request->hasFile('logo')) {
            $data['logo_path'] = $request->file('logo')->store('institutional/logo', 'public');
        }

        foreach (['login_background', 'login_logo'] as $field) {
            $pathField = $field.'_path';
            $removeField = 'remove_'.$field;

            if ($request->hasFile($field)) {
                $extension = strtolower($request->file($field)->extension());
                $data[$pathField] = $request->file($field)->storeAs(
                    'institutional/login',
                    Str::uuid().'.'.$extension,
                    'public'
                );
                $newPaths[] = $data[$pathField];
                if ($setting->{$pathField}) {
                    $oldPaths[] = $setting->{$pathField};
                }
            } elseif ($request->boolean($removeField) && $setting->{$pathField}) {
                $oldPaths[] = $setting->{$pathField};
                $data[$pathField] = null;
            }
        }

        unset($data['remove_login_background'], $data['remove_login_logo']);

        try {
            DB::transaction(fn () => $setting->update($data));
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($newPaths);
            throw $exception;
        }

        Storage::disk('public')->delete(array_values(array_diff($oldPaths, $newPaths)));
        InstitutionalSetting::clearCurrentCache();
        AuditService::record('configuracion_institucional.actualizada', $setting);

        return back()->with('status', 'Configuracion institucional actualizada.');
    }
}
