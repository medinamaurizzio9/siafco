<?php

namespace App\Http\Controllers;

use App\Models\AffiliationPayment;
use App\Models\PublicAffiliationRequest;
use App\Http\Requests\StoreAssistedAffiliationPaymentRequest;
use App\Services\PaymentActionAuthorization;
use App\Services\PaymentLifecycleService;
use App\Services\PublicAffiliationApprovalService;
use App\Services\AffiliateDeletionService;
use App\Support\PaymentStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PublicAffiliationAdminController extends Controller
{
    public function secretaryPayments(Request $request)
    {
        abort_unless($request->user()->isInternal() && $request->user()->hasPermission('payments.create'), 403);
        $data = $request->validate(['search' => ['nullable', 'string', 'max:100']]);
        $search = trim((string) ($data['search'] ?? ''));
        $applications = collect();

        if ($search !== '') {
            $applications = PublicAffiliationRequest::with(['person', 'sector', 'plan', 'payment', 'affiliate'])
                ->where(function ($query) use ($search) {
                    $query->where('request_code', 'like', "%{$search}%")
                        ->orWhereHas('person', function ($person) use ($search) {
                            $person->where('ci', 'like', "%{$search}%")
                                ->orWhere('full_name', 'like', "%{$search}%")
                                ->orWhere('phone', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        })
                        ->orWhereHas('affiliate', function ($affiliate) use ($search) {
                            $affiliate->where('ci', 'like', "%{$search}%")
                                ->orWhere('full_name', 'like', "%{$search}%")
                                ->orWhere('phone', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                })
                ->latest()
                ->limit(25)
                ->get();
            $applications->each(function (PublicAffiliationRequest $application) use ($request): void {
                $application->setAttribute('can_load_payment', $this->canLoadPayment($request, $application));
                $application->setAttribute('display_status', $application->affiliate?->status === 'activo'
                    ? 'activo'
                    : ($application->payment?->status ?? $application->status));
            });
        }

        return view('public-affiliation.admin.secretary-payments', compact('applications', 'search'));
    }

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

    public function show(
        Request $request,
        PublicAffiliationRequest $application,
        PaymentActionAuthorization $authorization
    )
    {
        $application->load(['person', 'affiliate', 'sector', 'plan', 'payment.registrar']);
        $duplicates = $application->payment?->transaction_number
            ? AffiliationPayment::where('transaction_number', $application->payment->transaction_number)
                ->whereKeyNot($application->payment->id)->with('affiliate')->get()
            : collect();
        $payment = $application->payment;
        $canLoadPayment = $this->canLoadPayment($request, $application);
        $canConfirmPayment = $payment ? $authorization->canConfirm($request->user(), $payment) : false;
        $canRejectPayment = $payment ? $authorization->canReject($request->user(), $payment) : false;

        return view('public-affiliation.admin.show', compact(
            'application',
            'duplicates',
            'canLoadPayment',
            'canConfirmPayment',
            'canRejectPayment'
        ));
    }

    public function take(PublicAffiliationRequest $application, PublicAffiliationApprovalService $service)
    {
        $service->take($application, auth()->id());
        return back()->with('status', 'Solicitud tomada para revisión.');
    }

    public function storePayment(
        StoreAssistedAffiliationPaymentRequest $request,
        PublicAffiliationRequest $application,
        PaymentLifecycleService $service
    ) {
        $service->registerAssistedPayment(
            $application,
            $request->validated(),
            $request->file('voucher'),
            $request->user()
        );

        return redirect()->route('public-affiliation.admin.show', $application)
            ->with('status', 'Pago registrado correctamente. Queda en revisión.');
    }

    public function approve(
        AffiliationPayment $payment,
        PaymentLifecycleService $service,
        PaymentActionAuthorization $authorization
    )
    {
        abort_unless($authorization->canAuthorizeConfirmation(auth()->user(), $payment), 403);
        $service->confirm($payment, auth()->user());
        return back()->with('status', 'Pago confirmado, afiliado activado y credencial generada.');
    }

    public function reject(
        Request $request,
        AffiliationPayment $payment,
        PaymentLifecycleService $service,
        PaymentActionAuthorization $authorization
    )
    {
        abort_unless($authorization->canAuthorizeRejection($request->user(), $payment), 403);
        $data = $request->validate(['rejection_reason' => ['required', 'string', 'max:1000']]);
        $service->reject($payment, auth()->user(), $data['rejection_reason']);
        return back()->with('status', 'Pago rechazado. El motivo queda disponible en el seguimiento.');
    }

    public function receipt(AffiliationPayment $payment)
    {
        abort_unless($payment->voucher_path && Storage::disk('local')->exists($payment->voucher_path), 404);
        return Storage::disk('local')->download($payment->voucher_path);
    }

    public function destroy(Request $request, PublicAffiliationRequest $application, AffiliateDeletionService $deletion)
    {
        abort_unless($application->affiliate, 404);
        Gate::authorize('delete', $application->affiliate);
        try {
            $deletion->delete(
                $application->affiliate,
                $request->user(),
                'Eliminación administrativa de solicitud pública confirmada por el usuario.'
            );
        } catch (ValidationException $exception) {
            return back()->with('error', collect($exception->errors())->flatten()->first() ?: 'Este registro no puede eliminarse.');
        }

        return redirect()->route('public-affiliation.admin.index')
            ->with('status', 'Registro eliminado correctamente.');
    }

    private function canLoadPayment(Request $request, PublicAffiliationRequest $application): bool
    {
        $payment = $application->payment;

        return $request->user()->isInternal()
            && $request->user()->hasPermission('payments.create')
            && $application->affiliate_id
            && $application->affiliate?->status !== 'activo'
            && $application->status === 'pending_payment'
            && (! $payment || PaymentStatus::isRejected($payment->status));
    }
}
