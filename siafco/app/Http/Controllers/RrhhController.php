<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpsertRrhhWorkerRequest;
use App\Models\RrhhPayPeriod;
use App\Models\RrhhProduct;
use App\Models\RrhhSale;
use App\Models\RrhhWorker;
use App\Services\RrhhWorkerService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RrhhController extends Controller
{
    public function __construct(private readonly RrhhWorkerService $workers) {}

    public function dashboard()
    {
        return view('rrhh.dashboard', ['metrics' => [
            'active_workers' => RrhhWorker::where('is_active', true)->count(),
            'virtual_workers' => RrhhWorker::where('type', RrhhWorker::TYPE_VIRTUAL)->where('is_active', true)->count(),
            'active_products' => RrhhProduct::where('is_active', true)->count(),
            'pending_sales' => RrhhSale::where('status', RrhhSale::STATUS_PENDING)->count(),
            'confirmed_sales' => RrhhSale::where('status', RrhhSale::STATUS_CONFIRMED)->count(),
            'confirmed_amount' => RrhhSale::where('status', RrhhSale::STATUS_CONFIRMED)->sum('total'),
            'confirmed_commissions' => RrhhSale::where('status', RrhhSale::STATUS_CONFIRMED)->sum('commission_total'),
        ]]);
    }

    public function index(Request $request)
    {
        $f = $request->validate(['search' => ['nullable', 'string', 'max:150'], 'type' => ['nullable', Rule::in(['scheduled', 'virtual'])], 'status' => ['nullable', Rule::in(['active', 'inactive'])]]);
        $q = RrhhWorker::query()->with('user')->when($f['search'] ?? null, fn ($q, $s) => $q->where(fn ($n) => $n->where('full_name', 'like', "%{$s}%")->orWhere('code', 'like', "%{$s}%")->orWhere('ci', 'like', "%{$s}%")))->when($f['type'] ?? null, fn ($q, $v) => $q->where('type', $v))->when(($f['status'] ?? null) === 'active', fn ($q) => $q->where('is_active', true))->when(($f['status'] ?? null) === 'inactive', fn ($q) => $q->where('is_active', false));

        return view('rrhh.workers.index', ['workers' => $q->orderBy('full_name')->paginate(15)->withQueryString(), 'filters' => $f]);
    }

    public function create()
    {
        return view('rrhh.workers.form', ['worker' => new RrhhWorker(['type' => 'scheduled', 'compensation_mode' => 'monthly', 'is_active' => true])]);
    }

    public function store(UpsertRrhhWorkerRequest $request)
    {
        $w = $this->workers->create($request->validated(), $request->user());

        return redirect()->route('rrhh.workers.show', $w)->with('status', 'Trabajador registrado correctamente.');
    }

    public function show(RrhhWorker $worker)
    {
        return view('rrhh.workers.show', ['worker' => $worker->load('user')]);
    }

    public function edit(RrhhWorker $worker)
    {
        return view('rrhh.workers.form', compact('worker'));
    }

    public function update(UpsertRrhhWorkerRequest $request, RrhhWorker $worker)
    {
        $this->workers->update($worker, $request->validated(), $request->user());

        return redirect()->route('rrhh.workers.show', $worker)->with('status', 'Ficha actualizada correctamente.');
    }

    public function toggle(Request $request, RrhhWorker $worker)
    {
        $this->workers->toggle($worker, $request->user());

        return back()->with('status', 'Estado actualizado correctamente.');
    }

    public function access(Request $request, RrhhWorker $worker)
    {
        abort_unless($request->user()->hasPermission('rrhh.access.manage') || $request->user()->hasPermission('rrhh.workers.manage'), 403);
        $data = $request->validate(['mode' => ['required', Rule::in(['link', 'create'])], 'access_email' => ['required', 'email', 'max:255']]);
        if ($data['mode'] === 'link') {
            $this->workers->linkExisting($worker, $data['access_email'], $request->user());

            return back()->with('status', 'Cuenta vinculada correctamente.');
        }

        $password = $this->workers->createAccess($worker, $data['access_email'], $request->user());

        return back()->with('status', 'Cuenta creada. Entrega la contraseña temporal por un canal seguro; se mostrará una sola vez.')->with('rrhh_temporary_password', $password);
    }

    public function unlink(Request $request, RrhhWorker $worker)
    {
        abort_unless($request->user()->hasPermission('rrhh.access.manage') || $request->user()->hasPermission('rrhh.workers.manage'), 403);
        $this->workers->unlink($worker, $request->user());

        return back()->with('status', 'Cuenta desvinculada. La cuenta general no fue eliminada ni modificada.');
    }

    public function self(Request $request)
    {
        $worker = $request->user()->rrhhWorker;
        abort_unless($worker, 403, 'La cuenta no está vinculada a una ficha de trabajador.');

        $sales = $worker->sales()->with('items')->orderByDesc('sold_on')->orderByDesc('id')->paginate(15);
        $confirmedCommissionTotal = $worker->sales()->where('status', RrhhSale::STATUS_CONFIRMED)->sum('commission_total');
        $periods = $worker->payPeriods()->latest('date_to')->latest('id')->get();

        return view('rrhh.self', compact('worker', 'sales', 'confirmedCommissionTotal', 'periods'));
    }

    public function selfSale(Request $request, RrhhSale $sale)
    {
        $worker = $request->user()->rrhhWorker;
        abort_unless($worker && $sale->worker_id === $worker->id, 404);

        return view('rrhh.self-sale', ['worker' => $worker, 'sale' => $sale->load('items')]);
    }

    public function selfPeriod(Request $request, RrhhPayPeriod $period)
    {
        $worker = $request->user()->rrhhWorker;
        abort_unless($worker && $period->worker_id === $worker->id, 404);

        return view('rrhh.self-period', ['worker' => $worker, 'period' => $period->load('lines')]);
    }
}
