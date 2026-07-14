<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreditApplication extends Model
{
    protected $fillable = ['affiliate_id', 'credit_product_id', 'amount', 'term_months', 'status'];
}
