<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RolePermissionOverride extends Model
{
    protected $fillable = ['role', 'permissions', 'updated_by'];

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
        ];
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
