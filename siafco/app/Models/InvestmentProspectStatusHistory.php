<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvestmentProspectStatusHistory extends Model
{
    protected $table = 'investment_prospect_status_history';
    protected $fillable = ['investment_prospect_id', 'from_status', 'to_status', 'changed_by_user_id', 'changed_at', 'reason'];
    protected function casts(): array { return ['changed_at' => 'datetime']; }
    public function user() { return $this->belongsTo(User::class, 'changed_by_user_id'); }
}
