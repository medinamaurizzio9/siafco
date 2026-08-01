<?php

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Mobile\V1\UpdatePasswordRequest;
use App\Http\Requests\Api\Mobile\V1\UpdateProfilePhotoRequest;
use App\Http\Requests\Api\Mobile\V1\UpdateProfileRequest;
use App\Http\Resources\Api\Mobile\V1\MobileProfileResource;
use App\Http\Responses\MobileApiResponse;
use App\Models\Affiliate;
use App\Models\Person;
use App\Services\AffiliatePasswordService;
use App\Services\AffiliatePhotoProcessor;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use Throwable;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        return MobileApiResponse::success([
            'profile' => new MobileProfileResource($request->user()->load('affiliate.sector', 'affiliate.plan')),
        ]);
    }

    public function update(UpdateProfileRequest $request)
    {
        $user = $request->user();
        $affiliate = $user->affiliate->loadMissing('person');
        $data = Arr::only($request->validated(), [
            'phone', 'email', 'address', 'birth_date', 'marital_status',
        ]);
        $before = $affiliate->only(array_keys($data));

        DB::transaction(function () use ($affiliate, $user, $data): void {
            $affiliate->update($data);

            if ($affiliate->person) {
                $affiliate->person->update(Arr::only($data, [
                    'phone', 'email', 'address', 'birth_date', 'marital_status',
                ]));
            }

            if (array_key_exists('email', $data)) {
                $user->update(['email' => $data['email']]);
            }
        });

        $affiliate->refresh();
        $changes = collect($affiliate->only(array_keys($before)))
            ->filter(fn ($value, $field) => (string) $value !== (string) ($before[$field] ?? null))
            ->all();

        AuditService::record('mobile_affiliate_profile_updated', $affiliate, [
            'fields' => array_keys($changes),
            'photo_changed' => false,
        ]);

        return MobileApiResponse::success([
            'profile' => new MobileProfileResource($user->fresh('affiliate.sector', 'affiliate.plan')),
        ], 'Perfil actualizado.');
    }

    public function updatePhoto(UpdateProfilePhotoRequest $request, AffiliatePhotoProcessor $photoProcessor)
    {
        $user = $request->user();
        $affiliate = $user->affiliate->loadMissing('person', 'credential');
        $oldPhoto = $affiliate->photo_path;
        $newPhoto = $photoProcessor->process($request->file('photo'));

        try {
            DB::transaction(function () use ($affiliate, $newPhoto): void {
                $affiliate->update(['photo_path' => $newPhoto]);
                $affiliate->person?->update(['photo' => $newPhoto]);

                if ($affiliate->credential) {
                    foreach ([$affiliate->credential->pdf_path, $affiliate->credential->png_path] as $path) {
                        if ($path) {
                            Storage::disk('public')->delete($path);
                        }
                    }
                    $affiliate->credential->update(['pdf_path' => null, 'png_path' => null]);
                }
            });
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($newPhoto);

            throw $exception;
        }

        if ($newPhoto && $oldPhoto && $oldPhoto !== $newPhoto && $this->photoIsUnshared($affiliate, $oldPhoto)) {
            Storage::disk('public')->delete($oldPhoto);
        }

        AuditService::record('mobile_affiliate_photo_updated', $affiliate, [
            'photo_changed' => true,
        ]);

        return MobileApiResponse::success([
            'profile' => new MobileProfileResource($user->fresh('affiliate.sector', 'affiliate.plan')),
        ], 'Perfil actualizado.');
    }

    public function updatePassword(UpdatePasswordRequest $request, AffiliatePasswordService $passwordService)
    {
        $user = $request->user();
        $wasForced = (bool) $user->must_change_password;
        try {
            $passwordService->assertAllowedPassword($request->validated('password'), $user, $user->affiliate);
        } catch (ValidationException $exception) {
            return MobileApiResponse::error('Los datos enviados no son validos.', 422, $exception->errors());
        }

        $user->forceFill([
            'password' => Hash::make($request->validated('password')),
            'must_change_password' => false,
        ])->save();

        $currentTokenId = PersonalAccessToken::findToken((string) $request->bearerToken())?->getKey()
            ?? $user->currentAccessToken()?->getKey();
        $user->tokens()
            ->when($currentTokenId, fn ($query) => $query->whereKeyNot($currentTokenId))
            ->delete();

        AuditService::record('mobile_affiliate_password_changed', $user->affiliate, [
            'forced' => $wasForced,
        ]);

        return MobileApiResponse::success([
            'profile' => new MobileProfileResource($user->fresh('affiliate.sector', 'affiliate.plan')),
        ], 'Contraseña actualizada.');
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
