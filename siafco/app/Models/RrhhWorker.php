<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RrhhWorker extends Model
{
    use HasFactory;

    public const TYPE_SCHEDULED = 'scheduled';

    public const TYPE_VIRTUAL = 'virtual';

    public const MODE_MONTHLY = 'monthly';

    public const MODE_DAILY = 'daily';

    public const MODE_COMMISSION_ONLY = 'commission_only';

    protected $fillable = ['code', 'user_id', 'type', 'full_name', 'ci', 'phone', 'email', 'position', 'joined_on', 'schedule_description', 'compensation_mode', 'agreed_amount', 'commissions_enabled', 'is_active', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['joined_on' => 'date', 'agreed_amount' => 'decimal:2', 'commissions_enabled' => 'boolean', 'is_active' => 'boolean'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function sales()
    {
        return $this->hasMany(RrhhSale::class, 'worker_id');
    }

    public function adjustments()
    {
        return $this->hasMany(RrhhAdjustment::class, 'worker_id');
    }

    public function payPeriods()
    {
        return $this->hasMany(RrhhPayPeriod::class, 'worker_id');
    }
}
