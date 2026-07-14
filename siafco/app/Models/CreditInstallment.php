<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreditInstallment extends Model
{
    protected $fillable = ['credit_application_id', 'number', 'due_date', 'amount', 'late_fee', 'status'];
}
