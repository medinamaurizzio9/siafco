<?php

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\MobileApiResponse;
use App\Models\InstitutionalSetting;
use App\Services\CredentialService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CredentialController extends Controller
{
    public function show(Request $request, CredentialService $credentialService)
    {
        $user = $request->user();
        $affiliate = $user?->affiliate?->loadMissing('sector', 'credential');

        if (! $affiliate || $user->user_type !== 'affiliate' || $user->role !== 'afiliado') {
            return MobileApiResponse::error('La API movil solo esta disponible para afiliados.', 403);
        }

        if (! $user->is_active || $affiliate->status !== 'activo') {
            return MobileApiResponse::error('La credencial movil solo esta disponible para afiliados activos.', 403, [
                'status' => [$affiliate->status],
            ]);
        }

        $credential = $affiliate->credential;
        if (! $credential || ! $credential->qr_path || ! Storage::disk('public')->exists($credential->qr_path)) {
            return MobileApiResponse::error('Aun no tienes una credencial digital disponible.', 404);
        }

        if (! $credential->isActive()) {
            return MobileApiResponse::error('La credencial digital no se encuentra vigente.', 403, [
                'credential_status' => [$credential->status],
            ]);
        }

        $institution = InstitutionalSetting::current();
        $credentialData = $credentialService->presentationData($affiliate, $credential);

        return MobileApiResponse::success([
            'credential' => [
                'institution_name' => $institution->institution_name,
                'affiliate_name' => $credentialData['full_name'],
                'registration_number' => $credentialData['registration_number'],
                'sector' => $credentialData['sector'],
                'regional' => $credentialData['regional'],
                'status' => $affiliate->status,
                'status_label' => $credentialData['status_label'],
                'issued_at' => $credentialData['issued_at'],
                'photo_url' => $this->photoUrl($affiliate->photo_path),
                'verification_url' => route('verify.show', $affiliate->verification_token),
                'qr_image' => $this->dataUri($credential->qr_path),
            ],
        ], 'Credencial movil disponible.');
    }

    private function photoUrl(?string $path): ?string
    {
        if (! $path || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        return Storage::disk('public')->url($path)
            .'?v='.Storage::disk('public')->lastModified($path);
    }

    private function dataUri(?string $path): ?string
    {
        if (! $path || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        $absolutePath = Storage::disk('public')->path($path);
        $mime = mime_content_type($absolutePath) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode(file_get_contents($absolutePath));
    }
}
