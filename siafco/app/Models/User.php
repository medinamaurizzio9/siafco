<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'ci',
        'phone',
        'position',
        'area',
        'photo_path',
        'person_id',
        'email',
        'role',
        'user_type',
        'password',
        'must_change_password',
        'is_active',
        'last_login_at',
        'last_login_ip',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function affiliate()
    {
        return $this->hasOne(Affiliate::class);
    }

    public function person()
    {
        return $this->belongsTo(Person::class);
    }

    public function investor()
    {
        return $this->hasOne(Investor::class, 'person_id', 'person_id');
    }

    public function uploadedStoreReceipts()
    {
        return $this->hasMany(StoreOrderReceipt::class, 'uploaded_by_user_id');
    }

    public function reviewedStoreReceipts()
    {
        return $this->hasMany(StoreOrderReceipt::class, 'reviewed_by_user_id');
    }

    public function hasRole(string|array $roles): bool
    {
        $service = app(\App\Services\RolePermissionService::class);
        $current = $service->normalizeRole($this->role);
        $roles = array_map(fn (string $role) => $service->normalizeRole($role), (array) $roles);

        return in_array($current, $roles, true);
    }

    public function hasPermission(string $permission): bool
    {
        if (! $this->isInternal()) {
            return false;
        }

        return in_array($permission, app(\App\Services\RolePermissionService::class)->permissionsForRole($this->role), true);
    }

    public function isInternal(): bool
    {
        if ($this->user_type === 'internal') {
            return true;
        }

        return $this->user_type === null
            && app(\App\Services\RolePermissionService::class)->isKnownInternalRole((string) $this->role);
    }

    public function roleLabel(): string
    {
        $role = app(\App\Services\RolePermissionService::class)->normalizeRole($this->role);

        return config("internal_roles.labels.{$role}", str($this->role)->headline()->toString());
    }

    public function photoUrl(): ?string
    {
        return $this->photo_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($this->photo_path) : null;
    }
}
