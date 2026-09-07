<?php

namespace App\Services;

use App\Models\InvestmentAdvisor;
use App\Models\InvestmentCrmSequence;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class InvestmentAdvisorService
{
    private ?array $accessCredentials = null;

    public function __construct(private readonly QrCodeService $qrCodeService, private readonly InvestmentAdvisorAccessService $accessService, private readonly InvestmentAdvisorPhotoProcessor $photoProcessor)
    {
    }

    public function create(array $data): InvestmentAdvisor
    {
        $photo=$data['photo']??null; unset($data['photo'],$data['remove_photo']);
        $photoPath=$photo?$this->photoProcessor->process($photo):null;
        if($photoPath)$data['photo_path']=$photoPath;
        try {
        return DB::transaction(function () use ($data) {
            $advisor = InvestmentAdvisor::create([
                ...$data,
                'advisor_number' => $this->generateAdvisorNumber(),
                'public_id' => (string) Str::uuid(),
                'public_token' => $this->generatePublicToken(),
                'created_by' => auth()->id(),
            ]);

            AuditService::record('investment_advisor_created', $advisor, [
                'advisor_id' => $advisor->id,
                'advisor_number' => $advisor->advisor_number,
            ]);

            $this->generateQr($advisor);
            $this->accessCredentials = $this->accessService->createAccess($advisor);

            return $advisor;
        });
        } catch (\Throwable $exception) { if($photoPath) Storage::disk('public')->delete($photoPath); throw $exception; }
    }

    public function pullAccessCredentials(): ?array
    {
        return tap($this->accessCredentials, fn () => $this->accessCredentials = null);
    }

    public function update(InvestmentAdvisor $advisor, array $data): InvestmentAdvisor
    {
        $photo=$data['photo']??null; $remove=(bool)($data['remove_photo']??false); unset($data['photo'],$data['remove_photo']);
        $newPath=$photo?$this->photoProcessor->process($photo):null; $oldPath=$advisor->photo_path;
        if($newPath)$data['photo_path']=$newPath; elseif($remove)$data['photo_path']=null;
        try { $updated=DB::transaction(function () use ($advisor, $data) {
            $wasActive = $advisor->is_active;
            $advisor->update($data);

            AuditService::record('investment_advisor_updated', $advisor, [
                'advisor_id' => $advisor->id,
                'advisor_number' => $advisor->advisor_number,
            ]);

            if ($wasActive !== $advisor->is_active) {
                AuditService::record(
                    $advisor->is_active ? 'investment_advisor_activated' : 'investment_advisor_deactivated',
                    $advisor,
                    ['advisor_id' => $advisor->id, 'advisor_number' => $advisor->advisor_number]
                );
            }

            return $advisor;
        });
        } catch (\Throwable $exception) { if($newPath) Storage::disk('public')->delete($newPath); throw $exception; }
        if(($newPath||$remove)&&$oldPath) Storage::disk('public')->delete($oldPath);
        return $updated;
    }

    public function generateAdvisorNumber(): string
    {
        $sequence = InvestmentCrmSequence::query()
            ->where('key', 'investment_advisor')
            ->lockForUpdate()
            ->firstOrFail();

        $number = $sequence->next_number;
        $sequence->update(['next_number' => $number + 1]);

        return sprintf('ASE-%04d', $number);
    }

    public function generatePublicToken(): string
    {
        do {
            $token = Str::random(64);
        } while (InvestmentAdvisor::query()->where('public_token', $token)->exists());

        return $token;
    }

    public function generateQr(InvestmentAdvisor $advisor): string
    {
        return $this->qrCodeService->png(
            route('investments.advisors.public', ['token' => $advisor->public_token]),
            $advisor->qrPath()
        );
    }
}
