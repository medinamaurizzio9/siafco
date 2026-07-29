<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class InternalUserService
{
    public function __construct(
        private readonly UserPasswordService $passwords,
        private readonly UserAccessService $access,
    ) {}

    public function create(array $data, ?UploadedFile $photo): User
    {
        $newPhoto = $this->storePhoto($photo);

        try {
            return DB::transaction(function () use ($data, $newPhoto) {
                $password = $data['use_ci_password']
                    ? $this->passwords->temporaryPasswordFromCi($data['ci'])
                    : $data['password'];

                $user = User::create([
                    ...$this->profileData($data),
                    'photo_path' => $newPhoto,
                    'role' => $data['role'],
                    'user_type' => 'internal',
                    'is_active' => $data['is_active'],
                    'password' => Hash::make($password),
                    'must_change_password' => true,
                ]);

                return $user;
            });
        } catch (Throwable $exception) {
            if ($newPhoto) {
                Storage::disk('public')->delete($newPhoto);
            }
            throw $exception;
        }
    }

    public function update(User $user, array $data, ?UploadedFile $photo): array
    {
        $oldPhoto = $user->photo_path;
        $newPhoto = $this->storePhoto($photo);
        $tracked = ['name', 'ci', 'phone', 'email', 'username', 'position', 'area', 'role', 'is_active'];
        $before = $user->only($tracked);

        try {
            DB::transaction(function () use ($user, $data, $newPhoto) {
                $user->update([
                    ...$this->profileData($data),
                    'photo_path' => $newPhoto ?: $user->photo_path,
                    'role' => $data['role'],
                    'is_active' => $user->is_active,
                ]);
            });
        } catch (Throwable $exception) {
            if ($newPhoto) {
                Storage::disk('public')->delete($newPhoto);
            }
            throw $exception;
        }

        if ($newPhoto && $oldPhoto) {
            Storage::disk('public')->delete($oldPhoto);
        }

        return ['before' => $before, 'after' => $user->fresh()->only($tracked)];
    }

    public function delete(User $user): void
    {
        DB::transaction(function () use ($user) {
            $this->access->invalidateSessions($user);
            $user->delete();
        });
    }

    public function restore(User $user): void
    {
        DB::transaction(fn () => $user->restore());
    }

    private function profileData(array $data): array
    {
        return [
            'name' => $data['name'],
            'ci' => $data['ci'],
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'],
            'username' => $data['username'],
            'position' => $data['position'] ?? null,
            'area' => $data['area'] ?? null,
        ];
    }

    private function storePhoto(?UploadedFile $photo): ?string
    {
        return $photo?->storeAs(
            'internal-users/photos',
            Str::uuid().'.'.$photo->extension(),
            'public'
        );
    }
}
