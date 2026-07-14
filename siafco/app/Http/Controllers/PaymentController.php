<?php

namespace App\Http\Controllers;

use App\Models\AffiliationPayment;
use App\Services\AuditService;
use App\Services\CredentialService;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $payments = AffiliationPayment::with('affiliate.sector')
            ->when($request->status, fn ($query, $status) => $query->where('status', $status))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('payments.index', compact('payments'));
    }

    public function updateProof(Request $request, AffiliationPayment $payment)
    {
        $data = $request->validate([
            'transaction_number' => ['required', 'string', 'max:120'],
            'voucher' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
        ]);

        if ($request->hasFile('voucher')) {
            $data['voucher_path'] = $request->file('voucher')->store('payments/vouchers', 'public');
        }

        $payment->update($data + ['status' => 'pendiente']);
        AuditService::record('pago.comprobante_registrado', $payment);

        return back()->with('status', 'Comprobante registrado.');
    }

    public function confirm(AffiliationPayment $payment, CredentialService $credentialService)
    {
        $payment->update([
            'status' => 'confirmado',
            'confirmed_by' => auth()->id(),
            'confirmed_at' => now(),
            'rejection_reason' => null,
        ]);

        $payment->affiliate->update(['status' => 'activo']);
        $credentialService->generate($payment->affiliate);
        AuditService::record('pago.confirmado', $payment);

        return back()->with('status', 'Pago confirmado y credencial generada.');
    }

    public function reject(Request $request, AffiliationPayment $payment)
    {
        $data = $request->validate(['rejection_reason' => ['required', 'string', 'max:500']]);

        $payment->update([
            'status' => 'rechazado',
            'confirmed_by' => auth()->id(),
            'confirmed_at' => now(),
            'rejection_reason' => $data['rejection_reason'],
        ]);

        $payment->affiliate->update(['status' => 'observado']);
        AuditService::record('pago.rechazado', $payment);

        return back()->with('status', 'Pago rechazado.');
    }
}
