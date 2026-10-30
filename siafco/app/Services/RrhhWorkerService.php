<?php

namespace App\Services;

use App\Models\RrhhWorker;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RrhhWorkerService
{
    public function create(array $data, User $actor): RrhhWorker
    {
        return DB::transaction(function () use ($data, $actor) {
            $last = RrhhWorker::query()->lockForUpdate()->orderByDesc('id')->first();
            $worker = RrhhWorker::create([...$data, 'code' => 'TRA-'.str_pad((string) (($last?->id ?? 0) + 1), 6, '0', STR_PAD_LEFT), 'created_by' => $actor->id, 'updated_by' => $actor->id]);
            AuditService::record('rrhh_worker_created', $worker, ['worker_code' => $worker->code, 'worker_type' => $worker->type]);

            return $worker;
        });
    }

    public function update(RrhhWorker $worker, array $data, User $actor): RrhhWorker
    {
        return DB::transaction(function () use ($worker, $data, $actor) {
            $before = $worker->only(['type', 'full_name', 'ci', 'phone', 'email', 'position', 'joined_on', 'schedule_description', 'compensation_mode', 'agreed_amount', 'commissions_enabled', 'is_active']);
            $worker->update([...$data, 'updated_by' => $actor->id]);
            AuditService::record('rrhh_worker_updated', $worker, ['worker_code' => $worker->code, 'fields' => array_keys(array_diff_assoc($worker->fresh()->only(array_keys($before)), $before))]);

            return $worker->fresh();
        });
    }

    public function toggle(RrhhWorker $worker, User $actor): void
    {
        DB::transaction(function () use ($worker, $actor) {
            $locked = RrhhWorker::query()->lockForUpdate()->findOrFail($worker->id);
            $locked->update(['is_active' => ! $locked->is_active, 'updated_by' => $actor->id]);
            AuditService::record($locked->is_active ? 'rrhh_worker_activated' : 'rrhh_worker_deactivated', $locked, ['worker_code' => $locked->code]);
        });
    }

    public function linkExisting(RrhhWorker $worker, string $email, User $actor): void
    {
        DB::transaction(function () use ($worker, $email, $actor) {
            $user = User::query()->where('email', mb_strtolower(trim($email)))->where('is_active', true)->first();
            if (! $user || ! $user->isInternal()) {
                throw ValidationException::withMessages(['access_email' => 'No existe una cuenta interna activa compatible con ese correo.']);
            }
            if (RrhhWorker::query()->where('user_id', $user->id)->whereKeyNot($worker->id)->exists()) {
                throw ValidationException::withMessages(['access_email' => 'La cuenta ya está vinculada a otro trabajador.']);
            }
            $worker->update(['user_id' => $user->id, 'email' => $worker->email ?: $user->email, 'updated_by' => $actor->id]);
            AuditService::record('rrhh_worker_access_linked', $worker, ['worker_code' => $worker->code, 'linked_user_id' => $user->id]);
        });
    }

    public function createAccess(RrhhWorker $worker, string $email, User $actor): string
    {
        return DB::transaction(function () use ($worker, $email, $actor) {
            if ($worker->user_id) {
                throw ValidationException::withMessages(['access_email' => 'El trabajador ya tiene una cuenta vinculada.']);
            }
            $email = mb_strtolower(trim($email));
            if (User::withTrashed()->where('email', $email)->exists()) {
                throw ValidationException::withMessages(['access_email' => 'El correo ya pertenece a una cuenta. Usa la opción de vincular.']);
            }
            $temporary = Str::password(16, true, true, false, false);
            $user = User::create(['name' => $worker->full_name, 'username' => 'trabajador_'.Str::lower(Str::random(10)), 'ci' => null, 'phone' => $worker->phone, 'email' => $email, 'role' => 'trabajador_rrhh', 'user_type' => 'internal', 'password' => Hash::make($temporary), 'must_change_password' => true, 'is_active' => true]);
            $worker->update(['user_id' => $user->id, 'email' => $email, 'updated_by' => $actor->id]);
            AuditService::record('rrhh_worker_access_linked', $worker, ['worker_code' => $worker->code, 'linked_user_id' => $user->id, 'account_created' => true]);

            return $temporary;
        });
    }

    public function unlink(RrhhWorker $worker, User $actor): void
    {
        DB::transaction(function () use ($worker, $actor) {
            $userId = $worker->user_id;
            $linkedUser = $worker->user;
            $worker->update(['user_id' => null, 'updated_by' => $actor->id]);
            if ($linkedUser?->hasRole('trabajador_rrhh')) {
                app(UserAccessService::class)->block($linkedUser);
            }
            AuditService::record('rrhh_worker_access_unlinked', $worker, ['worker_code' => $worker->code, 'linked_user_id' => $userId]);
        });
    }
}
