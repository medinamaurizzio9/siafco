<?php

namespace App\Http\Controllers\Investments;

use App\Http\Controllers\Controller;
use App\Models\InvestmentSetting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function edit()
    {
        return view('investments.settings.edit', ['setting' => InvestmentSetting::current()]);
    }

    public function update(Request $request)
    {
        $setting = InvestmentSetting::current();
        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'company_legal_name' => ['nullable', 'string', 'max:255'],
            'nit' => ['nullable', 'string', 'max:80'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:80'],
            'email' => ['nullable', 'email', 'max:255'],
            'receipt_logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:4096'],
            'currency' => ['required', 'string', 'max:10'],
            'share_unit_price' => ['required', 'numeric', 'min:0'],
            'minimum_shares' => ['required', 'integer', 'min:1'],
            'maximum_shares' => ['required', 'integer', 'min:1'],
            'monthly_return_percentage' => ['required', 'numeric', 'min:0'],
            'waiting_months' => ['required', 'integer', 'min:0'],
            'contract_years' => ['required', 'integer', 'min:1'],
            'reservation_days' => ['required', 'integer', 'min:1'],
            'maximum_shares_per_person' => ['nullable', 'boolean'],
            'renewal_enabled' => ['nullable', 'boolean'],
            'production_bonus_enabled' => ['nullable', 'boolean'],
            'extra_amount_enabled' => ['nullable', 'boolean'],
            'receipt_prefix' => ['required', 'string', 'max:30'],
            'next_receipt_number' => ['required', 'integer', 'min:1'],
            'receipt_legal_text' => ['nullable', 'string'],
            'alert_days_before_maturity' => ['required', 'integer', 'min:1'],
            'active' => ['nullable', 'boolean'],
        ]);

        if ($request->hasFile('receipt_logo')) {
            $data['receipt_logo'] = $request->file('receipt_logo')->store('investments/settings', 'public');
        }

        foreach (['maximum_shares_per_person', 'renewal_enabled', 'production_bonus_enabled', 'extra_amount_enabled', 'active'] as $flag) {
            $data[$flag] = $request->boolean($flag);
        }

        $setting->update($data);
        InvestmentSetting::clearCurrentCache();

        return back()->with('status', 'Configuracion de inversiones actualizada.');
    }
}
