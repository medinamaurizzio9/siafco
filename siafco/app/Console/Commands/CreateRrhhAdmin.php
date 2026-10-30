<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CreateRrhhAdmin extends Command
{
    protected $signature = 'rrhh:create-admin';

    protected $description = 'Crea interactivamente una cuenta administrativa exclusiva de Personal y Comisiones';

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('Este comando solo puede ejecutarse en un entorno local.');

            return self::FAILURE;
        }

        $name = trim((string) $this->ask('Nombre completo'));
        $email = mb_strtolower(trim((string) $this->ask('Correo electrónico')));
        $password = (string) $this->secret('Contraseña temporal');
        $confirmation = (string) $this->secret('Confirma la contraseña temporal');
        $validator = Validator::make(compact('name', 'email', 'password', 'confirmation'), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'regex:/[A-Za-z]/', 'regex:/[0-9]/', 'same:confirmation'],
        ], ['password.same' => 'Las contraseñas no coinciden.']);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = DB::transaction(function () use ($name, $email, $password) {
            $base = Str::limit(Str::slug(Str::before($email, '@'), '_'), 45, '');
            do {
                $username = ($base ?: 'rrhh').'_'.Str::lower(Str::random(8));
            } while (User::withTrashed()->where('username', $username)->exists());

            $user = User::create([
                'name' => $name,
                'username' => $username,
                'email' => $email,
                'role' => 'rrhh',
                'user_type' => 'internal',
                'password' => Hash::make($password),
                'must_change_password' => true,
                'is_active' => true,
            ]);
            AuditService::record('rrhh_admin_account_created', $user, ['created_user_id' => $user->id, 'role' => 'rrhh']);

            return $user;
        });

        $this->info("Cuenta RR. HH. creada para {$user->email}.");
        $this->line('El cambio de contraseña será obligatorio en el primer acceso.');

        return self::SUCCESS;
    }
}
