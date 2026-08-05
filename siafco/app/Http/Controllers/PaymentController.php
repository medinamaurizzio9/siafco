<?php

namespace App\Http\Controllers;

use App\Http\Requests\RejectPaymentRequest;
use App\Http\Requests\StoreManualPaymentRequest;
use App\Http\Requests\UpdateManualPaymentRequest;
use App\Http\Requests\VoidPaymentRequest;
use App\Models\Affiliate;
use App\Models\AffiliationPayment;
use App\Models\InstitutionalSetting;
use App\Models\User;
use App\Services\AuditService;
use App\Services\PaymentBalanceService;
use App\Services\PaymentLifecycleService;
use App\Services\PaymentReceiptService;
use App\Support\PaymentStatus;
use App\Support\TextNormalizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->hasPermission('payments.view'), 403);

        $payments = AffiliationPayment::with('affiliate.sector', 'registrar', 'cashier')
            ->when($request->status, fn ($query, $status) => $query->where('status', $status))
            ->when($request->payment_method, fn ($query, $method) => $query->where('payment_method', $method))
            ->when($request->source, fn ($query, $source) => $query->where('source', $source))
            ->when($request->registered_by, fn ($query, $id) => $query->where('registered_by', $id))
            ->when($request->confirmed_by, fn ($query, $id) => $query->where('confirmed_by', $id))
            ->when($request->date_from, fn ($query, $date) => $query->whereDate('paid_at', '>=', $date))
            ->when($request->date_to, fn ($query, $date) => $query->whereDate('paid_at', '<=', $date))
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('reference_number', 'like', "%{$search}%")
                        ->orWhere('transaction_number', 'like', "%{$search}%")
                        ->orWhereHas('affiliate', function ($affiliate) use ($search) {
                            $affiliate->where('full_name', 'like', "%{$search}%")
                                ->orWhere('ci', 'like', "%{$search}%")
                                ->orWhere('registration_number', 'like', "%{$search}%");
                        });
                });
            })
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('payments.index', [
            'payments' => $payments,
            'statuses' => PaymentStatus::allValues(),
            'users' => User::where('user_type', 'internal')->orderBy('name')->get(),
            'institution' => InstitutionalSetting::current(),
        ]);
    }

    public function create(Request $request, PaymentBalanceService $balances)
    {
        abort_unless($request->user()->hasPermission('payments.create'), 403);
        $selectedAffiliate = $request->integer('affiliate_id')
            ? Affiliate::with('plan')->findOrFail($request->integer('affiliate_id'))
            : null;

        return view('payments.form', [
            'payment' => new AffiliationPayment([
                'currency' => 'BOB',
                'paid_at' => now(),
                'status' => PaymentStatus::PENDING,
                'payment_method' => 'efectivo',
            ]),
            'affiliate' => $selectedAffiliate,
            'affiliates' => $selectedAffiliate ? collect([$selectedAffiliate]) : Affiliate::orderBy('full_name')->limit(100)->get(),
            'balance' => $selectedAffiliate ? $balances->summary($selectedAffiliate) : null,
        ]);
    }

    public function store(StoreManualPaymentRequest $request, PaymentLifecycleService $payments)
    {
        $data = $request->validated();
        $affiliate = Affiliate::findOrFail($data['affiliate_id']);

        $payment = $payments->createManual($affiliate, $data, $request->file('voucher'), $request->user());

        return redirect()->route('payments.show', $payment)->with('status', 'Pago registrado correctamente.');
    }

    public function show(Request $request, AffiliationPayment $payment, PaymentBalanceService $balances)
    {
        abort_unless($request->user()->hasPermission('payments.view'), 403);

        return view('payments.show', [
            'payment' => $payment->load('affiliate.plan', 'publicRequest', 'registrar', 'cashier', 'rejector', 'voider'),
            'balance' => $payment->affiliate ? $balances->summary($payment->affiliate) : null,
        ]);
    }

    public function edit(Request $request, AffiliationPayment $payment, PaymentBalanceService $balances)
    {
        abort_unless($request->user()->hasPermission('payments.update_pending'), 403);
        abort_unless(PaymentStatus::isEditable($payment->status), 403, 'Solo se pueden editar pagos pendientes o en revision.');

        return view('payments.form', [
            'payment' => $payment->load('affiliate.plan'),
            'affiliate' => $payment->affiliate,
            'affiliates' => collect([$payment->affiliate]),
            'balance' => $payment->affiliate ? $balances->summary($payment->affiliate) : null,
        ]);
    }

    public function update(UpdateManualPaymentRequest $request, AffiliationPayment $payment, PaymentLifecycleService $payments)
    {
        $payments->updatePending($payment, $request->validated(), $request->file('voucher'), $request->user());

        return redirect()->route('payments.show', $payment)->with('status', 'Pago actualizado.');
    }

    public function updateProof(Request $request, AffiliationPayment $payment)
    {
        $data = $request->validate([
            'transaction_number' => ['required', 'string', 'max:120'],
            'voucher' => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,application/pdf', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
        ]);

        if ($request->hasFile('voucher')) {
            $data['voucher_path'] = $request->file('voucher')->store('payments/vouchers', 'local');
        }

        $payment->update($data + ['status' => PaymentStatus::PENDING, 'source' => $payment->source ?: 'web']);
        AuditService::record('pago.comprobante_registrado', $payment, ['has_voucher' => $request->hasFile('voucher')]);

        return back()->with('status', 'Comprobante registrado.');
    }

    public function confirm(Request $request, AffiliationPayment $payment, PaymentLifecycleService $payments)
    {
        abort_unless($request->user()->hasPermission('payments.confirm'), 403);
        $payments->confirm($payment, $request->user());

        return back()->with('status', 'Pago confirmado y afiliacion actualizada.');
    }

    public function reject(RejectPaymentRequest $request, AffiliationPayment $payment, PaymentLifecycleService $payments)
    {
        $payments->reject($payment, $request->user(), $request->validated('rejection_reason'));

        return back()->with('status', 'Pago rechazado.');
    }

    public function void(VoidPaymentRequest $request, AffiliationPayment $payment, PaymentLifecycleService $payments)
    {
        $payments->void($payment, $request->user(), $request->validated('void_reason'));

        return back()->with('status', 'Pago anulado.');
    }

    public function voucher(Request $request, AffiliationPayment $payment)
    {
        abort_unless($request->user()->hasPermission('payments.view_receipt'), 403);
        abort_if(! $payment->voucher_path || ! Storage::disk('local')->exists($payment->voucher_path), 404);

        return Storage::disk('local')->response($payment->voucher_path);
    }

    public function receipt(Request $request, AffiliationPayment $payment, PaymentReceiptService $receipts)
    {
        abort_unless($request->user()->hasPermission('payments.view_receipt'), 403);

        return Response::make($receipts->output($payment), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="recibo-'.$payment->id.'.pdf"',
        ]);
    }

    public function downloadReceipt(Request $request, AffiliationPayment $payment, PaymentReceiptService $receipts)
    {
        abort_unless($request->user()->hasPermission('payments.download_receipt'), 403);
        AuditService::record('payment_receipt_downloaded', $payment, ['receipt_number' => $payment->receipt_number]);

        return Response::make($receipts->output($payment), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="recibo-'.$payment->id.'.pdf"',
        ]);
    }
}
