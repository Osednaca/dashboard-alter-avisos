<?php

namespace App\Console\Commands;

use App\Domain\Users\Enums\RoleEnum;
use App\Domain\Users\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateAdmin extends Command
{
    protected $signature = 'signage:admin';

    protected $description = 'Crear un administrador con correo y contraseña propios, sin modificar usuarios existentes';

    public function handle(): int
    {
        if (! $this->input->isInteractive()) {
            $this->error('Ejecuta este comando en una consola interactiva para introducir la contraseña de forma oculta.');

            return self::FAILURE;
        }

        $role = Role::query()->where('name', RoleEnum::SuperAdmin->value)->first();
        if (! $role) {
            $this->error('Primero ejecuta: php artisan db:seed --force');

            return self::FAILURE;
        }

        $identity = Validator::make([
            'name' => trim((string) $this->ask('Nombre')),
            'email' => mb_strtolower(trim((string) $this->ask('Correo electrónico'))),
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
        ]);

        if ($identity->fails()) {
            $this->error($identity->errors()->first());

            return self::FAILURE;
        }

        $password = (string) $this->secret('Contraseña (mínimo 12 caracteres)', false);
        $confirmation = (string) $this->secret('Repite la contraseña', false);
        $validation = Validator::make([
            'password' => $password,
            'password_confirmation' => $confirmation,
        ], ['password' => ['required', 'string', 'confirmed', Password::min(12), function ($attribute, $value, $fail) {
            if (strlen($value) > 72) {
                $fail('La contraseña debe ocupar como máximo 72 bytes.');
            }
        }]]);

        if ($validation->fails()) {
            $this->error($validation->errors()->first());

            return self::FAILURE;
        }

        DB::transaction(function () use ($identity, $password, $role): void {
            $user = User::query()->create([
                ...$identity->validated(),
                'password' => $password,
                'status' => 'active',
            ]);
            $user->roles()->attach($role);
        });

        $this->info('Administrador creado. Ya puedes iniciar sesión con tu correo y contraseña.');

        return self::SUCCESS;
    }
}
