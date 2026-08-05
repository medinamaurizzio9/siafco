<?php

namespace App\Http\Controllers\Admin\Store;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Store\UpdateStoreSettingRequest;
use App\Models\StoreSetting;
use App\Services\AuditService;
use App\Services\StoreSettingService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;

class SettingController extends Controller
{
    public function edit()
    {
        Gate::authorize('store.manage-settings');

        return view('admin.store.settings.edit', [
            'setting' => StoreSetting::current(),
        ]);
    }

    public function update(UpdateStoreSettingRequest $request, StoreSettingService $settings)
    {
        $result = $settings->update($request->validated(), $request->user());
        $setting = $result['setting'];

        AuditService::record('mini_tienda.configuracion_actualizada', $setting, [
            'fields' => array_keys(Arr::except($request->validated(), ['whatsapp_number'])),
            'whatsapp_changed' => $result['whatsapp_changed'],
            'whatsapp_old_hint' => $result['old_hint'],
            'whatsapp_new_hint' => $result['new_hint'],
        ]);

        return redirect()->route('admin.store.settings.edit')->with('status', 'Configuración de tienda actualizada.');
    }
}
