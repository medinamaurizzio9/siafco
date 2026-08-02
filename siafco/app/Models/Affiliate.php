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
        'photo_path',
        'birth_date',
        'marital_status',
        'registration_number',
        'status',
        'verification_token',
    ];

    protected function casts(): array
    {
        return ['birth_date' => 'date'];
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

    public function publicRequest()
    {
        return $this->hasOne(PublicAffiliationRequest::class)->latestOfMany();
    }

    public function storeOrders()
    {
        return $this->hasMany(StoreOrder::class);
    }

    public function storeCouponUsages()
    {
        return $this->hasMany(StoreCouponUsage::class);
    }

    public function benefitRedemptions()
    {
        return $this->hasMany(AffiliateBenefitRedemption::class);
    }
}
