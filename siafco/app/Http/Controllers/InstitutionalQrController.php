<?php

namespace App\Http\Controllers;

use App\Models\InstitutionalSetting;
use App\Services\AuditService;
use App\Support\TextNormalizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class InstitutionalQrController extends Controller
{
    public function show()
    {
        $setting = InstitutionalSetting::current();

        return view('institutional-qr.show', compact('setting'));
    }

    public function update(Request $request)
    {
        $oldPath = InstitutionalSetting::query()->value('payment_qr_path');
        InstitutionalSetting::clearCurrentCache();
        $setting = InstitutionalSetting::current();
        $data = $request->validate([
            'qr' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_qr' => ['nullable', 'boolean'],
            'payment_bank' => ['nullable', 'string', 'max:120'],
            'payment_holder' => ['nullable', 'string', 'max:255'],
            'payment_account' => ['nullable', 'string', 'max:120'],
            'payment_instructions' => ['nullable', 'string', 'max:2000'],
        ]);
        $data = TextNormalizer::fields($data, ['payment_bank', 'payment_holder', 'payment_instructions']);
        unset($data['qr'], $data['remove_qr']);

        $newPath = null;
        if ($request->hasFile('qr')) {
            $extension = strtolower($request->file('qr')->extension());
            $newPath = $request->file('qr')->storeAs(
                'institutional/payment',
                Str::uuid().'.'.$extension,
                'public'
            );
            $data['payment_qr_path'] = $newPath;
        } elseif ($request->boolean('remove_qr')) {
            $data['payment_qr_path'] = null;
        }

        try {
            DB::transaction(fn () => $setting->update($data));
        } catch (Throwable $exception) {
            if ($newPath) {
                Storage::disk('public')->delete($newPath);
            }
            report($exception);

            return back()->withErrors(['qr' => 'No se pudo actualizar el QR institucional. Inténtalo nuevamente.']);
        }

        InstitutionalSetting::clearCurrentCache();
        if ($oldPath && $oldPath !== $newPath && ($newPath || $request->boolean('remove_qr'))) {
            Storage::disk('public')->delete($oldPath);
        }
        AuditService::record('qr_institucional_pago.actualizado', $setting, [
            'qr_replaced' => (bool) $newPath,
            'qr_removed' => $request->boolean('remove_qr'),
        ]);

        return back()->with('status', 'QR institucional de pago actualizado correctamente.');
    }
}
