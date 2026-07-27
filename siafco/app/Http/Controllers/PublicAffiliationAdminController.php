<?php

namespace App\Http\Controllers;

use App\Models\AffiliationPayment;
use App\Models\PublicAffiliationRequest;
use App\Services\PublicAffiliationApprovalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PublicAffiliationAdminController extends Controller
{
    public function index(Request $request)
    {
        $applications = PublicAffiliationRequest::with(['person', 'sector', 'plan', 'payment'])
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->search, fn ($q, $v) => $q->where(function ($q) use ($v) {
                $q->where('request_code', 'like', "%{$v}%")
                    ->orWhereHas('person', fn ($q) => $q->where('full_name', 'like', "%{$v}%")->orWhere('ci', 'like', "%{$v}%"))
                    ->orWhereHas('payment', fn ($q) => $q->where('transaction_number', 'like', "%{$v}%"));
            }))
            ->latest()->paginate(15)->withQueryString();

        return view('public-affiliation.admin.index', compact('applications'));
    }

    public function show(PublicAffiliationRequest $application)
    {
        $application->load(['person', 'affiliate', 'sector', 'plan', 'payment']);
        $duplicates = $application->payment?->transaction_number
            ? AffiliationPayment::where('transaction_number', $application->payment->transaction_number)
                ->whereKeyNot($application->payment->id)->with('affiliate')->get()
            : collect();
        return view('public-affiliation.admin.show', compact('application', 'duplicates'));
    }

    public function take(PublicAffiliationRequest $application, PublicAffiliationApprovalService $service)
    {
        $service->take($application, auth()->id());
        return back()->with('status', 'Solicitud tomada para revisión.');
    }

    public function approve(AffiliationPayment $payment, PublicAffiliationApprovalService $service)
    {
        $service->approve($payment, auth()->id());
        return back()->with('status', 'Pago confirmado, afiliado activado y credencial generada.');
    }

    public function reject(Request $request, AffiliationPayment $payment, PublicAffiliationApprovalService $service)
    {
        $data = $request->validate(['rejection_reason' => ['required', 'string', 'max:1000']]);
        $service->reject($payment, auth()->id(), $data['rejection_reason']);
        return back()->with('status', 'Pago rechazado. El motivo queda disponible en el seguimiento.');
    }

    public function receipt(AffiliationPayment $payment)
    {
        abort_unless($payment->voucher_path && Storage::disk('local')->exists($payment->voucher_path), 404);
        return Storage::disk('local')->download($payment->voucher_path);
    }
}
