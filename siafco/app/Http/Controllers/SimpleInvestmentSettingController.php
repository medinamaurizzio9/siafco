<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSimpleInvestmentSettingRequest;
use App\Models\SimpleInvestmentSetting;
use App\Services\AuditService;

class SimpleInvestmentSettingController extends Controller
{
    public function edit()
    {
        return view('simple-investments.settings.edit', ['settings' => SimpleInvestmentSetting::current()]);
    }

    public function update(UpdateSimpleInvestmentSettingRequest $request)
    {
        $settings = SimpleInvestmentSetting::current();
        $oldPrice = $settings->unit_price;
        $settings->update([...$request->validated(), 'updated_by' => $request->user()->id]);
        AuditService::record('simple_investment_settings_updated', $settings, ['fields' => array_keys($request->validated()), 'unit_price_changed' => $oldPrice !== $settings->unit_price]);

        return back()->with('status', 'Configuración actualizada. Los contratos anteriores conservan su precio histórico.');
    }
}
