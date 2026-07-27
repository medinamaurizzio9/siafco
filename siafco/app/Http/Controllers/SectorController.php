<?php

namespace App\Http\Controllers;

use App\Models\Sector;
use App\Services\AuditService;
use App\Support\TextNormalizer;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SectorController extends Controller
{
    public function index()
    {
        return view('sectors.index', ['sectors' => Sector::latest()->paginate(10)]);
    }

    public function create()
    {
        return view('sectors.form', ['sector' => new Sector()]);
    }

    public function store(Request $request)
    {
        $sector = Sector::create($this->validated($request));
        AuditService::record('sector.creado', $sector);

        return redirect()->route('sectors.index')->with('status', 'Sector creado.');
    }

    public function edit(Sector $sector)
    {
        return view('sectors.form', compact('sector'));
    }

    public function update(Request $request, Sector $sector)
    {
        $sector->update($this->validated($request, $sector));
        AuditService::record('sector.actualizado', $sector);

        return redirect()->route('sectors.index')->with('status', 'Sector actualizado.');
    }

    public function destroy(Sector $sector)
    {
        $sector->delete();
        AuditService::record('sector.eliminado', $sector);

        return back()->with('status', 'Sector eliminado.');
    }

    private function validated(Request $request, ?Sector $sector = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:20', Rule::unique('sectors')->ignore($sector)],
            'regional' => ['nullable', 'string', 'max:255'],
            'institution' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]) + ['is_active' => $request->boolean('is_active')];

        return TextNormalizer::fields($data, ['name', 'code', 'regional', 'institution']);
    }
}
