<?php

namespace App\Http\Controllers;

use App\Models\AffiliateBenefit;
use App\Services\AuditService;
use App\Support\TextNormalizer;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AffiliateBenefitController extends Controller
{
    public function index()
    {
        return view('affiliate-benefits.index', [
            'benefits' => AffiliateBenefit::orderBy('order')->orderBy('title')->get(),
        ]);
    }

    public function create()
    {
        return view('affiliate-benefits.form', ['benefit' => new AffiliateBenefit()]);
    }

    public function store(Request $request)
    {
        $benefit = AffiliateBenefit::create($this->validated($request));
        AuditService::record('beneficio.creado', $benefit);

        return redirect()->route('affiliate-benefits.index')->with('status', 'Servicio o beneficio creado.');
    }

    public function edit(AffiliateBenefit $affiliateBenefit)
    {
        return view('affiliate-benefits.form', ['benefit' => $affiliateBenefit]);
    }

    public function update(Request $request, AffiliateBenefit $affiliateBenefit)
    {
        $affiliateBenefit->update($this->validated($request));
        AuditService::record('beneficio.actualizado', $affiliateBenefit);

        return redirect()->route('affiliate-benefits.index')->with('status', 'Servicio o beneficio actualizado.');
    }

    public function destroy(AffiliateBenefit $affiliateBenefit)
    {
        $affiliateBenefit->delete();
        AuditService::record('beneficio.eliminado', $affiliateBenefit);

        return back()->with('status', 'Servicio o beneficio eliminado.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'icon' => ['required', Rule::in(['card', 'credit', 'calculator', 'history', 'gift', 'news', 'support', 'investment'])],
            'route_name' => ['nullable', 'string', 'max:160'],
            'external_url' => ['nullable', 'url', 'max:500'],
            'active' => ['nullable', 'boolean'],
            'visible_when_pending' => ['nullable', 'boolean'],
            'order' => ['required', 'integer', 'min:0', 'max:999'],
        ]);

        $data = TextNormalizer::fields($data, ['title', 'description']);
        $data['active'] = $request->boolean('active');
        $data['visible_when_pending'] = $request->boolean('visible_when_pending');

        return $data;
    }
}
