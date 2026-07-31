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

    public function hasRole(string|array $roles): bool
    {
        return in_array($this->role, (array) $roles, true);
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, config("internal_roles.roles.{$this->role}", []), true);
    }

    public function isInternal(): bool
    {
        return $this->user_type === 'internal';
    }

    public function roleLabel(): string
    {
        return config("internal_roles.labels.{$this->role}", str($this->role)->headline()->toString());
    }

    public function photoUrl(): ?string
    {
        return $this->photo_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($this->photo_path) : null;
    }
}
