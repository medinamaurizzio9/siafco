<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpsertSimpleInvestmentInvestorRequest;
use App\Models\SimpleInvestmentInvestor;
use App\Services\SimpleInvestmentInvestorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class SimpleInvestmentInvestorController extends Controller
{
    public function __construct(private readonly SimpleInvestmentInvestorService $service) {}

    public function index(Request $request)
    {
        Gate::authorize('viewAny', SimpleInvestmentInvestor::class);
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:150'], 'status' => ['nullable', 'in:active,inactive']]);
        $query = SimpleInvestmentInvestor::query()->when($filters['search'] ?? null, fn ($query, $search) => $query->where(fn ($nested) => $nested->where('full_name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%")->orWhere('ci', 'like', "%{$search}%")))->when(($filters['status'] ?? null) === 'active', fn ($query) => $query->where('is_active', true))->when(($filters['status'] ?? null) === 'inactive', fn ($query) => $query->where('is_active', false));

        return view('simple-investments.investors.index', ['investors' => $query->latest()->paginate(15)->withQueryString(), 'filters' => $filters]);
    }

    public function create()
    {
        Gate::authorize('create', SimpleInvestmentInvestor::class);

        return view('simple-investments.investors.form', ['investor' => new SimpleInvestmentInvestor(['registered_on' => today(), 'is_active' => true])]);
    }

    public function store(UpsertSimpleInvestmentInvestorRequest $request)
    {
        $investor = $this->service->create($request->validated(), $request->user());

        return redirect()->route('simple-investments.investors.show', $investor)->with('status', 'Inversionista registrado correctamente.');
    }

    public function show(SimpleInvestmentInvestor $investor)
    {
        Gate::authorize('view', $investor);

        return view('simple-investments.investors.show', ['investor' => $investor->load(['portalAccess', 'contracts' => fn ($query) => $query->latest()])]);
    }

    public function edit(SimpleInvestmentInvestor $investor)
    {
        Gate::authorize('update', $investor);

        return view('simple-investments.investors.form', compact('investor'));
    }

    public function update(UpsertSimpleInvestmentInvestorRequest $request, SimpleInvestmentInvestor $investor)
    {
        $this->service->update($investor, $request->validated(), $request->user());

        return redirect()->route('simple-investments.investors.show', $investor)->with('status', 'Inversionista actualizado correctamente.');
    }

    public function photo(SimpleInvestmentInvestor $investor)
    {
        Gate::authorize('view', $investor);
        abort_unless($investor->photo_path && Storage::disk('local')->exists($investor->photo_path), 404);

        return Storage::disk('local')->response($investor->photo_path);
    }
}
