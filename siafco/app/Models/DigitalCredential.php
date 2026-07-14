<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DigitalCredential extends Model
{
    protected $fillable = ['affiliate_id', 'qr_path', 'pdf_path', 'png_path', 'generated_at'];

    protected function casts(): array
    {
        return ['generated_at' => 'datetime'];
    }

    public function affiliate()
    {
        return $this->belongsTo(Affiliate::class);
    }
}
