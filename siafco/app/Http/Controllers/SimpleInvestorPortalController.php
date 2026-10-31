<?php

namespace App\Http\Controllers;

use App\Models\SimpleInvestmentContract;
use App\Models\SimpleInvestmentDocumentVersion;
use App\Models\SimpleInvestmentPortalNotification;
use App\Services\SimpleInvestmentScheduleService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class SimpleInvestorPortalController extends Controller
{
    public function __construct(private readonly SimpleInvestmentScheduleService $schedule) {}

    private function investor(Request $request)
    {
        return $request->attributes->get('simpleInvestor');
    }

    public function dashboard(Request $request)
    {
        $investor = $this->investor($request)->load(['contracts' => fn ($q) => $q->with(['units', 'scheduleNotices'])->latest()]);
        $signed = $investor->contracts->where('status', SimpleInvestmentContract::SIGNED);

        return view('simple-investor.dashboard', ['investor' => $investor, 'signed' => $signed,
            'nextDate' => $this->schedule->portalDates($signed)['next'],
            'notifications' => $investor->portalNotifications()->latest('generated_at')->limit(4)->get()]);
    }

    public function profile(Request $request)
    {
        return view('simple-investor.profile', ['investor' => $this->investor($request)]);
    }

    public function updateProfile(Request $request)
    {
        $data = $request->validate(['phone' => ['nullable', 'string', 'max:30'], 'email' => ['nullable', 'email', 'max:255'], 'address' => ['nullable', 'string', 'max:1000'], 'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120']]);
        $investor = $this->investor($request);
        $photo = $data['photo'] ?? null;
        unset($data['photo']);
        if ($photo instanceof UploadedFile) {
            $old = $investor->photo_path;
            $data['photo_path'] = $photo->store('simple-investments/investors', 'local');
            $investor->update($data);
            if ($old) {
                Storage::disk('local')->delete($old);
            }
        } else {
            $investor->update($data);
        }

        return back()->with('status', 'Datos de contacto actualizados.');
    }

    public function photo(Request $request)
    {
        $investor = $this->investor($request);
        abort_unless($investor->photo_path && Storage::disk('local')->exists($investor->photo_path), 404);

        return Storage::disk('local')->response($investor->photo_path, null, ['Cache-Control' => 'no-store, private']);
    }

    public function contracts(Request $request)
    {
        return view('simple-investor.contracts', ['contracts' => $this->investor($request)->contracts()->with('currentDocument')->latest()->get()]);
    }

    public function contract(Request $request, SimpleInvestmentContract $contract)
    {
        abort_unless($contract->investor_id === $this->investor($request)->id, 404);

        return view('simple-investor.contract', ['contract' => $contract->load(['units', 'currentDocument', 'scheduleNotices'])]);
    }

    public function document(Request $request, SimpleInvestmentContract $contract, SimpleInvestmentDocumentVersion $document)
    {
        abort_unless($contract->investor_id === $this->investor($request)->id && $document->contract_id === $contract->id && $document->status === SimpleInvestmentDocumentVersion::VERIFIED, 404);
        abort_unless(Storage::disk('local')->exists($document->path), 404);

        return Storage::disk('local')->download($document->path, $document->original_name, ['Cache-Control' => 'no-store, private']);
    }

    public function units(Request $request)
    {
        return view('simple-investor.units', ['units' => $this->investor($request)->contracts()->with('units.contract')->get()->flatMap->units]);
    }

    public function calendar(Request $request)
    {
        $contracts = $this->investor($request)->contracts()->where('status', SimpleInvestmentContract::SIGNED)->with('scheduleNotices')->get();

        return view('simple-investor.calendar', ['calendar' => $this->schedule->portalDates($contracts)]);
    }

    public function notifications(Request $request)
    {
        $items = $this->investor($request)->portalNotifications()->latest('generated_at')->paginate(20);
        $this->investor($request)->portalNotifications()->whereNull('displayed_at')->update(['displayed_at' => now()]);

        return view('simple-investor.notifications', ['notifications' => $items]);
    }

    public function read(Request $request, SimpleInvestmentPortalNotification $notification)
    {
        abort_unless($notification->investor_id === $this->investor($request)->id, 404);
        $notification->update(['displayed_at' => $notification->displayed_at ?: now(), 'read_at' => now()]);

        return back();
    }
}
