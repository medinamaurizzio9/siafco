<?php

namespace App\Http\Controllers\Investments;

use App\Http\Controllers\Controller;
use App\Models\Investor;
use App\Models\ShareReservation;
use App\Services\InvestmentService;
use Illuminate\Http\Request;

class ShareReservationController extends Controller
{
    public function index(Request $request)
    {
        return view('investments.reservations.index', [
            'reservations' => ShareReservation::with('investor.person')
                ->when($request->status, fn ($query, $status) => $query->where('status', $status))
                ->latest()
                ->paginate(12)
                ->withQueryString(),
        ]);
    }

    public function create(Request $request)
    {
        return view('investments.reservations.form', [
            'reservation' => new ShareReservation(),
            'investors' => Investor::with('person')->orderByDesc('id')->limit(100)->get(),
            'selectedInvestor' => $request->investor_id,
        ]);
    }

    public function store(Request $request, InvestmentService $service)
    {
        $data = $request->validate([
            'investor_id' => ['required', 'exists:investors,id'],
            'shares_quantity' => ['required', 'integer', 'min:1'],
            'reservation_date' => ['nullable', 'date'],
            'amount_paid' => ['nullable', 'numeric', 'min:0'],
            'payment_reference' => ['nullable', 'string', 'max:255'],
            'payment_method' => ['nullable', 'string', 'max:80'],
            'status' => ['nullable', 'in:pending,active,converted,expired,cancelled'],
            'notes' => ['nullable', 'string'],
        ]);

        $reservation = $service->createReservation(Investor::findOrFail($data['investor_id']), $data);

        return redirect()->route('investments.reservations.show', $reservation)->with('status', 'Reserva registrada.');
    }

    public function show(ShareReservation $reservation)
    {
        return view('investments.reservations.show', ['reservation' => $reservation->load('investor.person')]);
    }

    public function convert(ShareReservation $reservation, InvestmentService $service)
    {
        $lot = $service->createLot($reservation->investor, [
            'purchase_date' => now()->toDateString(),
            'shares_quantity' => $reservation->shares_quantity,
            'payment_method' => $reservation->payment_method ?: 'Reserva',
            'payment_reference' => $reservation->payment_reference,
            'notes' => 'Convertido desde reserva '.$reservation->id,
        ], $reservation);

        return redirect()->route('investments.lots.show', $lot)->with('status', 'Reserva convertida en lote pendiente de aprobacion.');
    }

    public function close(Request $request, ShareReservation $reservation)
    {
        $data = $request->validate([
            'status' => ['required', 'in:expired,cancelled'],
            'closure_reason' => ['required', 'string', 'max:255'],
            'support_document' => ['nullable', 'file', 'max:4096'],
        ]);

        if ($request->hasFile('support_document')) {
            $data['support_document'] = $request->file('support_document')->store('investments/reservations', 'public');
        }

        $reservation->update($data);

        return back()->with('status', 'Reserva cerrada con respaldo.');
    }
}
