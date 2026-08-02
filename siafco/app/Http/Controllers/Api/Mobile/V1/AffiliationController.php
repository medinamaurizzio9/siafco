<?php

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Mobile\V1\StoreAffiliationRequest;
use App\Http\Requests\Api\Mobile\V1\SubmitAffiliationPaymentRequest;
use App\Http\Resources\Api\Mobile\V1\AffiliationRequestResource;
use App\Http\Resources\Api\Mobile\V1\MobileProfileResource;
use App\Http\Responses\MobileApiResponse;
use App\Models\AffiliationPlan;
use App\Models\AuditLog;
use App\Models\InstitutionalSetting;
use App\Models\MobileApiIdempotencyKey;
use App\Models\PublicAffiliationRequest;
use App\Models\Sector;
use App\Services\AffiliatePhotoProcessor;
use App\Services\PublicAffiliationService;
use App\Support\PublicAffiliationCatalogs;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class AffiliationController extends Controller
{
    private const PAYMENT_SCOPE = 'mobile.v1.affiliation-payment';
    private const PAYMENT_STATUSES = ['pending_payment', 'payment_submitted', 'rejected'];

    public function catalogs()
    {
        $cacheKey = 'mobile.v1.catalogs.'
            .config('siafco.terms_version', '2026.1').'.'
            .config('siafco.privacy_version', '2026.1');

        return MobileApiResponse::success(Cache::remember($cacheKey, 300, function () {
            $institution = InstitutionalSetting::current();

            return [
                'sectors' => Sector::query()
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get(['id', 'name', 'code', 'regional', 'institution'])
                    ->map(fn (Sector $sector) => [
                        'id' => $sector->id,
                        'name' => $sector->name,
                        'code' => $sector->code,
                        'regional' => $sector->regional,
                        'institution' => $sector->institution,
                    ])
                    ->all(),
                'plans' => AffiliationPlan::query()
                    ->where('is_active', true)
                    ->where(fn ($q) => $q->whereNull('valid_from')->orWhereDate('valid_from', '<=', today()))
                    ->where(fn ($q) => $q->whereNull('valid_until')->orWhereDate('valid_until', '>=', today()))
                    ->orderBy('name')
                    ->get()
                    ->map(fn (AffiliationPlan $plan) => [
                        'id' => $plan->id,
                        'sector_id' => $plan->sector_id,
                        'name' => $plan->name,
                        'type' => $plan->type,
                        'currency' => $plan->currency,
                        'affiliation_fee' => (float) $plan->affiliation_fee,
                        'credential_fee' => (float) $plan->credential_fee,
                        'total_amount' => $plan->total_amount,
                        'description' => $plan->description,
                        'payment_instructions' => $plan->payment_instructions,
                    ])
                    ->all(),
                'regionals' => PublicAffiliationCatalogs::REGIONALS,
                'issued_in' => PublicAffiliationCatalogs::issuedInOptions(),
                'marital_statuses' => PublicAffiliationCatalogs::MARITAL_STATUSES,
                'institution' => [
                    'name' => $institution->institution_name,
                    'email' => $institution->email,
                    'phone' => $institution->phone,
                    'address' => $institution->address,
                    'payment_bank' => $institution->payment_bank,
                    'payment_holder' => $institution->payment_holder,
                    'payment_account' => $institution->payment_account,
                    'payment_instructions' => $institution->payment_instructions,
                    'terms_version' => config('siafco.terms_version', '2026.1'),
                    'privacy_version' => config('siafco.privacy_version', '2026.1'),
                ],
            ];
        }));
    }

    public function store(
        StoreAffiliationRequest $request,
        PublicAffiliationService $service,
        AffiliatePhotoProcessor $photoProcessor
    ) {
        $data = $request->validated();
        $photo = $photoProcessor->process($request->file('photo'));

        try {
            $application = $service->register($data, $photo, $request->ip(), $request->userAgent());
        } catch (ValidationException $exception) {
            Storage::disk('public')->delete($photo);
            $errors = $exception->errors();
            if (array_key_exists('ci', $errors) || array_key_exists('email', $errors)) {
                return MobileApiResponse::error('Ya existe una cuenta o solicitud asociada. Inicia sesión para continuar.', 409);
            }

            throw $exception;
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($photo);
            throw $exception;
        }

        $user = $application->user()->with('affiliate.sector', 'affiliate.plan')->firstOrFail();
        if ($application->getAttribute('mobile_user_created') !== true) {
            return MobileApiResponse::success([
                'affiliation_request' => new AffiliationRequestResource($application->fresh('plan', 'payment', 'affiliate')),
            ], 'Solicitud registrada.', 201);
        }

        $token = $user->createToken($data['device_name'] ?? 'SIAFCO Android', ['mobile'])->plainTextToken;
        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->save();

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'mobile_affiliation_request_created',
            'auditable_type' => $application::class,
            'auditable_id' => $application->id,
            'metadata' => [
                'request_code' => $application->request_code,
                'terms_version' => config('siafco.terms_version', '2026.1'),
                'privacy_version' => config('siafco.privacy_version', '2026.1'),
                'device_name' => mb_substr((string) ($data['device_name'] ?? 'SIAFCO Android'), 0, 120),
            ],
            'ip_address' => $request->ip(),
        ]);

        return MobileApiResponse::success([
            'token_type' => 'Bearer',
            'access_token' => $token,
            'profile' => new MobileProfileResource($user->fresh('affiliate.sector', 'affiliate.plan')),
            'affiliation_request' => new AffiliationRequestResource($application->fresh('plan', 'payment', 'affiliate')),
        ], 'Solicitud registrada.', 201);
    }

    public function show(Request $request)
    {
        $application = $this->currentApplication($request);

        if (! $application) {
            return MobileApiResponse::error('No existe una solicitud vinculada a tu cuenta.', 404);
        }

        return MobileApiResponse::success([
            'affiliation_request' => new AffiliationRequestResource($application),
        ]);
    }

    public function submitPayment(SubmitAffiliationPaymentRequest $request, PublicAffiliationService $service)
    {
        $application = $this->currentApplication($request);
        if (! $application) {
            return MobileApiResponse::error('No existe una solicitud vinculada a tu cuenta.', 404);
        }

        if (! in_array($application->status, self::PAYMENT_STATUSES, true)) {
            return MobileApiResponse::error('La solicitud no admite el envío de pagos en su estado actual.', 409, [
                'status' => [$application->status],
            ]);
        }

        $idempotencyKey = trim((string) $request->header('Idempotency-Key'));
        if ($idempotencyKey === '') {
            return $this->processPayment($request, $service, $application, false);
        }

        $requestHash = $this->paymentRequestHash($request, $request->validated());

        return DB::transaction(function () use ($request, $service, $application, $idempotencyKey, $requestHash) {
            $entry = MobileApiIdempotencyKey::query()
                ->where('user_id', $request->user()->id)
                ->where('scope', self::PAYMENT_SCOPE)
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($entry) {
                if (! hash_equals($entry->request_hash, $requestHash)) {
                    return MobileApiResponse::error('La clave de idempotencia ya fue utilizada con datos diferentes.', 409);
                }

                if ($entry->status === 'completed' && $entry->response_body && $entry->response_status) {
                    return response()->json($entry->response_body, $entry->response_status);
                }

                return MobileApiResponse::error('La operación con esta clave todavía está en proceso.', 409);
            }

            $entry = MobileApiIdempotencyKey::create([
                'user_id' => $request->user()->id,
                'scope' => self::PAYMENT_SCOPE,
                'idempotency_key' => $idempotencyKey,
                'request_hash' => $requestHash,
                'status' => 'processing',
            ]);

            $response = $this->processPayment($request, $service, $application, false);
            $entry->update([
                'status' => 'completed',
                'response_status' => $response->getStatusCode(),
                'response_body' => $response->getData(true),
            ]);

            return $response;
        });
    }

    private function processPayment(
        SubmitAffiliationPaymentRequest $request,
        PublicAffiliationService $service,
        PublicAffiliationRequest $application,
        bool $idempotent
    ): JsonResponse {
        $receipt = $request->file('receipt')?->store('affiliation-receipts', 'local');

        try {
            $service->submitPayment($application, $request->validated(), $receipt);
        } catch (ValidationException $exception) {
            if ($receipt) {
                Storage::disk('local')->delete($receipt);
            }

            return MobileApiResponse::error('La solicitud no admite el envío de pagos en su estado actual.', 409, $exception->errors());
        } catch (Throwable $exception) {
            if ($receipt) {
                Storage::disk('local')->delete($receipt);
            }
            throw $exception;
        }

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'mobile_affiliation_payment_submitted',
            'auditable_type' => $application::class,
            'auditable_id' => $application->id,
            'metadata' => [
                'request_code' => $application->request_code,
                'receipt_uploaded' => (bool) $receipt,
                'idempotency_key_hash' => $request->header('Idempotency-Key')
                    ? hash('sha256', $request->header('Idempotency-Key'))
                    : null,
            ],
            'ip_address' => $request->ip(),
        ]);

        return MobileApiResponse::success([
            'idempotent' => $idempotent,
            'affiliation_request' => new AffiliationRequestResource($application->fresh('plan', 'payment', 'affiliate')),
        ], 'Pago enviado para revisión.');
    }

    private function currentApplication(Request $request): ?PublicAffiliationRequest
    {
        return PublicAffiliationRequest::query()
            ->with('plan', 'payment', 'affiliate')
            ->where('user_id', $request->user()->id)
            ->latest()
            ->first();
    }

    private function paymentRequestHash(SubmitAffiliationPaymentRequest $request, array $data): string
    {
        $payload = [
            'transaction_number' => $this->normalizedText($data['transaction_number']),
            'payment_date' => $data['payment_date'],
            'paid_amount' => number_format((float) $data['paid_amount'], 2, '.', ''),
            'payer_name' => $this->normalizedText($data['payer_name']),
            'bank_name' => $this->normalizedText($data['bank_name'] ?? ''),
            'file_sha256' => $request->file('receipt')
                ? hash_file('sha256', $request->file('receipt')->getRealPath())
                : null,
        ];

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    private function normalizedText(?string $value): string
    {
        return (string) str($value ?? '')->squish()->upper();
    }
}
