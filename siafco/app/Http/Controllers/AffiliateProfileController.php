<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateOwnAffiliateProfileRequest;
use App\Models\Affiliate;
use App\Models\AffiliationPayment;
use App\Models\Person;
use App\Services\AffiliatePhotoProcessor;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class AffiliateProfileController extends Controller
{
    public function show(Request $request)
    {
        $affiliate = $this->affiliate($request);
        $payments = $affiliate->payments()
            ->with('plan')
            ->latest('payment_date')
            ->latest('created_at')
            ->paginate(10)
            ->withQueryString();

        return view('affiliate-profile.show', compact('affiliate', 'payments'));
    }

    public function update(
        UpdateOwnAffiliateProfileRequest $request,
        AffiliatePhotoProcessor $photoProcessor
    ) {
        $affiliate = $this->affiliate($request)->loadMissing('person', 'user', 'credential');
        $data = Arr::only($request->validated(), [
            'phone', 'email', 'address', 'birth_date', 'marital_status',
        ]);
        $before = $affiliate->only(array_keys($data));
        $oldPhoto = $affiliate->photo_path;
        $newPhoto = $request->hasFile('photo') ? $photoProcessor->process($request->file('photo')) : null;

        DB::transaction(function () use ($affiliate, $data, $newPhoto): void {
            if ($newPhoto) {
                $data['photo_path'] = $newPhoto;
            }

            $affiliate->update($data);
            $affiliate->person?->update([
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'],
                'address' => $data['address'] ?? null,
                'birth_date' => $data['birth_date'] ?? null,
                'marital_status' => $data['marital_status'] ?? null,
                'photo' => $data['photo_path'] ?? $affiliate->person?->photo,
            ]);
            $affiliate->user?->update(['email' => $data['email']]);

            if ($newPhoto && $affiliate->credential) {
                foreach ([$affiliate->credential->pdf_path, $affiliate->credential->png_path] as $path) {
                    if ($path) {
                        Storage::disk('public')->delete($path);
                    }
                }
                $affiliate->credential->update(['pdf_path' => null, 'png_path' => null]);
            }
        });

        if ($newPhoto && $oldPhoto && $oldPhoto !== $newPhoto && $this->photoIsUnshared($affiliate, $oldPhoto)) {
            Storage::disk('public')->delete($oldPhoto);
        }

        $affiliate->refresh();
        $changes = collect($affiliate->only(array_keys($before)))
            ->filter(fn ($value, $field) => (string) $value !== (string) ($before[$field] ?? null))
            ->all();

        AuditService::record('affiliate_profile_updated', $affiliate, [
            'fields' => array_keys($changes),
            'before' => Arr::only($before, array_keys($changes)),
            'after' => $changes,
            'photo_changed' => (bool) $newPhoto,
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
        ]);

        return redirect()->route('affiliate.profile.show')->with(
            'status',
            $newPhoto ? 'Tu perfil y fotografía fueron actualizados correctamente.' : 'Tu perfil fue actualizado correctamente.'
        );
    }

    public function showPaymentReceipt(Request $request, AffiliationPayment $payment): Response
    {
        $affiliate = $this->affiliate($request);
        abort_unless($payment->affiliate_id === $affiliate->id, 403);
        abort_unless($payment->voucher_path, 404);

        $disk = Storage::disk('local')->exists($payment->voucher_path) ? 'local' : 'public';
        abort_unless(Storage::disk($disk)->exists($payment->voucher_path), 404);

        $mime = Storage::disk($disk)->mimeType($payment->voucher_path);
        abort_unless(in_array($mime, [
            'application/pdf', 'image/jpeg', 'image/png', 'image/webp',
        ], true), 404);

        return Storage::disk($disk)->response(
            $payment->voucher_path,
            basename($payment->voucher_path),
            ['Content-Disposition' => 'inline; filename="'.basename($payment->voucher_path).'"']
        );
    }

    private function affiliate(Request $request): Affiliate
    {
        $affiliate = $request->user()?->affiliate;
        if (! $affiliate) {
            Log::warning('Authenticated affiliate user has no affiliate record.', [
                'user_id' => $request->user()?->id,
                'ip' => $request->ip(),
            ]);
            abort(404, 'No existe una ficha de afiliado vinculada a tu cuenta.');
        }

        return $affiliate->loadMissing('sector', 'plan', 'person', 'user');
    }

    private function photoIsUnshared(Affiliate $affiliate, string $path): bool
    {
        if (! str_starts_with($path, 'affiliates/photos/')) {
            return false;
        }

        return ! Affiliate::whereKeyNot($affiliate->id)->where('photo_path', $path)->exists()
            && ! Person::whereKeyNot($affiliate->person_id)->where('photo', $path)->exists();
    }
}
