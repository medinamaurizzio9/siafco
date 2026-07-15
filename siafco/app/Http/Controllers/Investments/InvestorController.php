<?php

namespace App\Http\Controllers\Investments;

use App\Http\Controllers\Controller;
use App\Models\Investor;
use App\Models\Person;
use App\Services\InvestmentService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InvestorController extends Controller
{
    public function index(Request $request)
    {
        $investors = Investor::with('person', 'type')
            ->when($request->search, function ($query, $search) {
                $query->where('investor_number', 'like', "%{$search}%")
                    ->orWhereHas('person', fn ($person) => $person
                        ->where('full_name', 'like', "%{$search}%")
                        ->orWhere('ci', 'like', "%{$search}%"));
            })
            ->when($request->status, fn ($query, $status) => $query->where('status', $status))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('investments.investors.index', compact('investors'));
    }

    public function create()
    {
        return view('investments.investors.form', ['investor' => new Investor(), 'person' => new Person()]);
    }

    public function store(Request $request, InvestmentService $service)
    {
        $data = $this->validated($request);
        $personData = collect($data)->only(['full_name', 'ci', 'ci_complement', 'issued_in', 'phone', 'email', 'address', 'birth_date', 'marital_status'])->all();

        if ($request->hasFile('photo')) {
            $personData['photo'] = $request->file('photo')->store('people/photos', 'public');
        }

        $investor = $service->createInvestor($personData, collect($data)->only(['status', 'start_date', 'notes'])->all());

        return redirect()->route('investments.investors.show', $investor)->with('status', 'Accionista registrado sin duplicar persona por CI.');
    }

    public function show(Investor $investor)
    {
        return view('investments.investors.show', [
            'investor' => $investor->load('person', 'type', 'reservations', 'lots.periods', 'receipts'),
        ]);
    }

    public function edit(Investor $investor)
    {
        return view('investments.investors.form', ['investor' => $investor->load('person'), 'person' => $investor->person]);
    }

    public function update(Request $request, Investor $investor)
    {
        $data = $this->validated($request, $investor);
        $personData = collect($data)->only(['full_name', 'ci_complement', 'issued_in', 'phone', 'email', 'address', 'birth_date', 'marital_status'])->all();

        if ($request->hasFile('photo')) {
            $personData['photo'] = $request->file('photo')->store('people/photos', 'public');
        }

        $investor->person->update($personData);
        $investor->update(collect($data)->only(['status', 'start_date', 'notes'])->all());

        return redirect()->route('investments.investors.show', $investor)->with('status', 'Accionista actualizado.');
    }

    private function validated(Request $request, ?Investor $investor = null): array
    {
        return $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'ci' => [$investor ? 'sometimes' : 'required', 'string', 'max:30', Rule::unique('people')->ignore($investor?->person_id)],
            'ci_complement' => ['nullable', 'string', 'max:20'],
            'issued_in' => ['nullable', 'string', 'max:40'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'birth_date' => ['nullable', 'date'],
            'marital_status' => ['nullable', 'string', 'max:80'],
            'photo' => ['nullable', 'image', 'max:4096'],
            'status' => ['required', 'in:prospect,reserved,active,suspended,completed,cancelled'],
            'start_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);
    }
}
