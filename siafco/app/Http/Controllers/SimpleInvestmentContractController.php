<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSimpleInvestmentContractRequest;
use App\Models\SimpleInvestmentContract;
use App\Models\SimpleInvestmentInvestor;
use App\Models\SimpleInvestmentSetting;
use App\Services\SimpleInvestmentContractService;
use App\Services\SimpleInvestmentScheduleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SimpleInvestmentContractController extends Controller
{
    public function __construct(private readonly SimpleInvestmentContractService $service) {}

    public function index(Request $request)
    {
        Gate::authorize('viewAny', SimpleInvestmentContract::class);
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:150'], 'status' => ['nullable', 'in:draft,pending_signature,signed,void']]);
        $query = SimpleInvestmentContract::query()->with('investor')->when($filters['search'] ?? null, fn ($query, $search) => $query->where(fn ($nested) => $nested->where('contract_number', 'like', "%{$search}%")->orWhereHas('investor', fn ($investor) => $investor->where('full_name', 'like', "%{$search}%")->orWhere('ci', 'like', "%{$search}%"))))->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status));

        return view('simple-investments.contracts.index', ['contracts' => $query->latest()->paginate(15)->withQueryString(), 'filters' => $filters]);
    }

    public function create(Request $request)
    {
        Gate::authorize('create', SimpleInvestmentContract::class);

        return view('simple-investments.contracts.form', ['contract' => new SimpleInvestmentContract(['registered_on' => today(), 'quantity' => 1]), 'investors' => SimpleInvestmentInvestor::where('is_active', true)->orderBy('full_name')->get(), 'settings' => SimpleInvestmentSetting::current(), 'selectedInvestor' => $request->integer('investor')]);
    }

    public function store(StoreSimpleInvestmentContractRequest $request)
    {
        $contract = $this->service->create($request->validated(), $request->user());

        return redirect()->route('simple-investments.contracts.show', $contract)->with('status', 'Contrato creado como borrador.');
    }

    public function show(SimpleInvestmentContract $contract, SimpleInvestmentScheduleService $schedule)
    {
        Gate::authorize('view', $contract);

        $contract->load('investor', 'responsible', 'units', 'documentVersions.uploader', 'documentVersions.reviewer', 'currentDocument', 'scheduleNotices');

        return view('simple-investments.contracts.show', ['contract' => $contract, 'schedule' => $schedule->projections($contract)]);
    }

    public function print(SimpleInvestmentContract $contract)
    {
        Gate::authorize('view', $contract);

        return view('simple-investments.contracts.print', ['contract' => $contract->load('investor', 'responsible', 'units')]);
    }

    public function pending(SimpleInvestmentContract $contract)
    {
        Gate::authorize('transition', $contract);
        $this->service->markPending($contract);

        return back()->with('status', 'Contrato marcado como pendiente de firma.');
    }

    public function sign(Request $request, SimpleInvestmentContract $contract)
    {
        Gate::authorize('transition', $contract);
        $data = $request->validate(['signed_on' => ['required', 'date', 'before_or_equal:today']]);
        $this->service->sign($contract, $request->user(), $data['signed_on']);

        return back()->with('status', 'Contrato firmado y unidades emitidas correctamente.');
    }

    public function void(Request $request, SimpleInvestmentContract $contract)
    {
        Gate::authorize('transition', $contract);
        $data = $request->validate(['void_reason' => ['required', 'string', 'min:5', 'max:1000']]);
        $this->service->void($contract, $request->user(), $data['void_reason']);

        return back()->with('status', 'Contrato anulado sin eliminar su trazabilidad.');
    }
}
