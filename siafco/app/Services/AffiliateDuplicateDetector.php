<?php

namespace App\Services;

use App\Models\Affiliate;
use App\Models\Person;

class AffiliateDuplicateDetector
{
    public function forAffiliate(Affiliate $affiliate): array
    {
        $affiliate->loadMissing('person');

        return [
            'ci' => $this->peopleBy('ci', $affiliate->ci, $affiliate->person_id),
            'email' => $this->peopleBy('email', $affiliate->email, $affiliate->person_id),
            'phone' => $this->peopleBy('phone', $affiliate->phone, $affiliate->person_id),
            'name_birth_date' => $this->peopleByNameAndBirthDate($affiliate),
        ];
    }

    private function peopleBy(string $field, ?string $value, ?int $currentPersonId)
    {
        if (! $value) {
            return collect();
        }

        return Person::query()
            ->where($field, $value)
            ->when($currentPersonId, fn ($query) => $query->whereKeyNot($currentPersonId))
            ->limit(5)
            ->get(['id', 'full_name', 'ci', 'email', 'phone', 'birth_date']);
    }

    private function peopleByNameAndBirthDate(Affiliate $affiliate)
    {
        if (! $affiliate->full_name || ! $affiliate->birth_date) {
            return collect();
        }

        return Person::query()
            ->where('full_name', $affiliate->full_name)
            ->whereDate('birth_date', $affiliate->birth_date)
            ->when($affiliate->person_id, fn ($query) => $query->whereKeyNot($affiliate->person_id))
            ->limit(5)
            ->get(['id', 'full_name', 'ci', 'email', 'phone', 'birth_date']);
    }
}
