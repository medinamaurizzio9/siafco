<?php

namespace App\Services;

use App\Models\SimpleInvestmentInvestor;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SimpleInvestmentInvestorService
{
    public function __construct(private readonly SimpleInvestmentSequenceService $sequences) {}

    public function create(array $data, User $actor): SimpleInvestmentInvestor
    {
        $newPhoto = $this->storePhoto($data['photo'] ?? null);

        try {
            return DB::transaction(function () use ($data, $actor, $newPhoto) {
                $investor = SimpleInvestmentInvestor::create([
                    ...Arr::except($data, 'photo'),
                    'code' => $this->sequences->next('investor', 'INV'),
                    'identity_key' => $this->identityKey($data),
                    'photo_path' => $newPhoto,
                    'created_by' => $actor->id,
                ]);
                AuditService::record('simple_investment_investor_created', $investor, ['investor_code' => $investor->code]);

                return $investor;
            });
        } catch (Throwable $exception) {
            if ($newPhoto) {
                Storage::disk('local')->delete($newPhoto);
            }

            throw $exception;
        }
    }

    public function update(SimpleInvestmentInvestor $investor, array $data, User $actor): SimpleInvestmentInvestor
    {
        $oldPhoto = $investor->photo_path;
        $newPhoto = $this->storePhoto($data['photo'] ?? null);

        try {
            $updated = DB::transaction(function () use ($investor, $data, $actor, $newPhoto) {
                $attributes = [...Arr::except($data, 'photo'), 'identity_key' => $this->identityKey($data), 'updated_by' => $actor->id];
                if ($newPhoto) {
                    $attributes['photo_path'] = $newPhoto;
                }
                $dirty = array_keys(array_diff_assoc($attributes, $investor->getAttributes()));
                $investor->update($attributes);
                AuditService::record('simple_investment_investor_updated', $investor, ['investor_code' => $investor->code, 'fields' => $dirty]);

                return $investor->refresh();
            });
        } catch (Throwable $exception) {
            if ($newPhoto) {
                Storage::disk('local')->delete($newPhoto);
            }

            throw $exception;
        }

        if ($newPhoto && $oldPhoto) {
            Storage::disk('local')->delete($oldPhoto);
        }

        return $updated;
    }

    public function identityKey(array $data): string
    {
        return mb_strtoupper(implode('|', [trim($data['ci']), trim($data['ci_complement'] ?? ''), trim($data['expedition_department'] ?? '')]));
    }

    private function storePhoto(mixed $photo): ?string
    {
        return $photo instanceof UploadedFile ? $photo->store('simple-investments/investors', 'local') : null;
    }
}
