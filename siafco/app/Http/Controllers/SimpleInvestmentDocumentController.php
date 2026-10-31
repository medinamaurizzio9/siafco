<?php

namespace App\Http\Controllers;

use App\Models\SimpleInvestmentContract;
use App\Models\SimpleInvestmentDocumentVersion;
use App\Services\SimpleInvestmentDocumentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class SimpleInvestmentDocumentController extends Controller
{
    public function __construct(private readonly SimpleInvestmentDocumentService $service) {}

    public function store(Request $request, SimpleInvestmentContract $contract)
    {
        Gate::authorize('uploadDocument', $contract);
        $request->validate(['document' => ['required', 'file', 'max:'.config('simple_investments.document_max_kb', 10240)]]);
        $this->service->upload($contract, $request->file('document'), $request->user());

        return back()->with('status', 'Documento cargado como una nueva versión privada.');
    }

    public function view(SimpleInvestmentContract $contract, SimpleInvestmentDocumentVersion $document)
    {
        $this->authorizeDocument($contract, $document);

        return Storage::disk('local')->response($document->path, $document->original_name, ['Content-Type' => $document->mime]);
    }

    public function download(SimpleInvestmentContract $contract, SimpleInvestmentDocumentVersion $document)
    {
        $this->authorizeDocument($contract, $document);

        return Storage::disk('local')->download($document->path, $document->original_name, ['Content-Type' => $document->mime]);
    }

    public function verify(Request $request, SimpleInvestmentContract $contract, SimpleInvestmentDocumentVersion $document)
    {
        Gate::authorize('verifyDocument', $contract);
        $this->assertBelongsToContract($contract, $document);
        $this->service->review($contract, $document, $request->user(), SimpleInvestmentDocumentVersion::VERIFIED, null);

        return back()->with('status', 'Documento verificado administrativamente.');
    }

    public function observe(Request $request, SimpleInvestmentContract $contract, SimpleInvestmentDocumentVersion $document)
    {
        Gate::authorize('verifyDocument', $contract);
        $this->assertBelongsToContract($contract, $document);
        $data = $request->validate(['review_notes' => ['required', 'string', 'min:5', 'max:1000']]);
        $this->service->review($contract, $document, $request->user(), SimpleInvestmentDocumentVersion::OBSERVED, $data['review_notes']);

        return back()->with('status', 'Documento observado. Puede cargarse una nueva versión corregida.');
    }

    private function authorizeDocument(SimpleInvestmentContract $contract, SimpleInvestmentDocumentVersion $document): void
    {
        Gate::authorize('viewDocument', $contract);
        $this->assertBelongsToContract($contract, $document);
        abort_unless(Storage::disk('local')->exists($document->path), 404);
    }

    private function assertBelongsToContract(SimpleInvestmentContract $contract, SimpleInvestmentDocumentVersion $document): void
    {
        abort_unless($document->contract_id === $contract->id, 404);
    }
}
