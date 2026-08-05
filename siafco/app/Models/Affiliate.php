<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Affiliate extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'person_id',
        'sector_id',
        'affiliation_plan_id',
        'full_name',
        'ci',
        'phone',
        'email',
        'address',
        'regional',
        'institution',
        'position',
        'affiliate_type',
        'administrative_notes',
        'photo_path',
        'birth_date',
        'marital_status',
        'registration_number',
        'status',
        'status_changed_at',
        'status_changed_by',
        'status_reason',
        'deleted_by',
        'deletion_reason',
        'verification_token',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'status_changed_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function person()
    {
        return $this->belongsTo(Person::class);
    }

    public function sector()
    {
        return $this->belongsTo(Sector::class);
    }

    public function plan()
    {
        return $this->belongsTo(AffiliationPlan::class, 'affiliation_plan_id');
    }

    public function payments()
    {
        return $this->hasMany(AffiliationPayment::class);
    }

    public function credential()
    {
        return $this->hasOne(DigitalCredential::class)->latestOfMany();
    }

    public function statusChanger()
    {
        return $this->belongsTo(User::class, 'status_changed_by');
    }

    public function publicRequest()
    {
        return $this->hasOne(PublicAffiliationRequest::class)->latestOfMany();
    }
}
