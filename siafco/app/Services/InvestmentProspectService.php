<?php

namespace App\Services;

use App\Models\InvestmentAdvisor;
use App\Models\InvestmentCrmSequence;
use App\Models\InvestmentProspect;
use App\Models\InvestmentProspectAssignment;
use App\Models\InvestmentProspectStatusHistory;
use App\Support\InvestmentProspectStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InvestmentProspectService
{
    public function createFromAdvisorQr(
        InvestmentAdvisor $advisor,
        array $data,
        ?string $ip,
        ?string $userAgent
    ): array {
        return $this->create($advisor, $data, 'advisor_qr', $ip, $userAgent);
    }

    public function createManual(InvestmentAdvisor $advisor, array $data): array
    {
        unset($data['advisor_id'], $data['service_rating'], $data['service_rating_at'], $data['service_rating_source']);
        return $this->create($advisor, $data, 'advisor_manual', null, null);
    }

    private function create(InvestmentAdvisor $advisor, array $data, string $source, ?string $ip, ?string $userAgent): array
    {
        abort_unless($advisor->isActive(), 404);

        return DB::transaction(function () use ($advisor, $data, $source, $ip, $userAgent) {
            $sequence = InvestmentCrmSequence::query()
                ->where('key', 'investment_prospect')
                ->lockForUpdate()
                ->firstOrFail();

            $phone = $this->cleanPhone($data['phone']);
            $phoneNormalized = $this->normalizePhone($phone);
            $existing = $this->findExistingByPhone($phoneNormalized);

            if ($existing) {
                return ['status' => 'existing', 'prospect' => $existing];
            }

            $capturedAt = now();
            $prospect = InvestmentProspect::create([
                'prospect_number' => $this->generateProspectNumber($sequence),
                'public_id' => (string) Str::uuid(),
                'full_name' => trim($data['full_name']),
                'phone' => $phone,
                'phone_normalized' => $phoneNormalized,
                'requested_shares' => $data['requested_shares'],
                'preferred_contact_method' => $data['preferred_contact_method'],
                'original_advisor_id' => $advisor->id,
                'current_advisor_id' => $advisor->id,
                'status' => InvestmentProspectStatus::CAPTURED,
                'source' => $source,
                'service_rating' => $source === 'advisor_qr' ? ($data['service_rating'] ?? null) : null,
                'service_rating_at' => $source === 'advisor_qr' && filled($data['service_rating'] ?? null) ? $capturedAt : null,
                'service_rating_source' => $source === 'advisor_qr' && filled($data['service_rating'] ?? null) ? 'public_form' : null,
                'captured_at' => $capturedAt,
                'contact_consent_at' => $capturedAt,
                'capture_ip' => $ip,
                'capture_user_agent' => $userAgent ? mb_substr($userAgent, 0, 1000) : null,
            ]);
            InvestmentProspectStatusHistory::create(['investment_prospect_id' => $prospect->id, 'from_status' => null, 'to_status' => InvestmentProspectStatus::CAPTURED, 'changed_by_user_id' => null, 'changed_at' => $capturedAt]);
            InvestmentProspectAssignment::create(['investment_prospect_id' => $prospect->id, 'from_advisor_id' => null, 'to_advisor_id' => $advisor->id, 'assigned_by_user_id' => null, 'assigned_at' => $capturedAt, 'reason' => $source === 'advisor_manual' ? 'Registro manual del asesor.' : 'Captación mediante QR del asesor.']);

            AuditService::record($source === 'advisor_manual' ? 'investment_prospect_captured_manual' : 'investment_prospect_captured', $prospect, [
                'prospect_id' => $prospect->id,
                'prospect_number' => $prospect->prospect_number,
                'advisor_id' => $advisor->id,
                'advisor_number' => $advisor->advisor_number,
                'requested_shares' => $prospect->requested_shares,
                'contact_method' => $prospect->preferred_contact_method,
                'source' => $source,
            ]);

            if ($prospect->service_rating) AuditService::record('investment_prospect_service_rated', $prospect, ['prospect_id'=>$prospect->id,'prospect_number'=>$prospect->prospect_number,'advisor_id'=>$advisor->id,'rating'=>$prospect->service_rating]);

            return ['status' => 'created', 'prospect' => $prospect];
        });
    }

    public function generateProspectNumber(InvestmentCrmSequence $sequence): string
    {
        $number = $sequence->next_number;
        $sequence->update(['next_number' => $number + 1]);

        return sprintf('PRO-%06d', $number);
    }

    public function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/[^0-9]/', '', $phone) ?? '';

        return strlen($digits) === 8 ? '591'.$digits : $digits;
    }

    public function findExistingByPhone(string $phoneNormalized): ?InvestmentProspect
    {
        return InvestmentProspect::query()->where('phone_normalized', $phoneNormalized)->first();
    }

    private function cleanPhone(string $phone): string
    {
        $phone = trim($phone);
        $hasPlus = str_starts_with($phone, '+');
        $digits = preg_replace('/[^0-9]/', '', $phone) ?? '';

        return ($hasPlus && $digits !== '' ? '+' : '').$digits;
    }
}
