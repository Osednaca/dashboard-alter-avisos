<?php

namespace Database\Seeders;

use App\Domain\Users\Enums\RoleEnum;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Los datos demo solo se permiten en local/testing. Usa php artisan db:seed --force y php artisan signage:admin.');
        }

        $accounts = [
            ['name' => 'Valentina Ríos', 'email' => 'admin@signagetv.co', 'job_title' => 'Directora de operaciones', 'roles' => [RoleEnum::SuperAdmin->value]],
            ['name' => 'Andrés Gómez', 'email' => 'operaciones@signagetv.co', 'job_title' => 'Operador de red', 'roles' => [RoleEnum::Operator->value]],
            ['name' => 'Laura Martínez', 'email' => 'campanas@signagetv.co', 'job_title' => 'Gestora de campañas', 'roles' => [RoleEnum::CampaignManager->value]],
            ['name' => 'Carlos Peña', 'email' => 'soporte@signagetv.co', 'job_title' => 'Soporte técnico', 'roles' => [RoleEnum::Support->value]],
        ];

        foreach ($accounts as $account) {
            $user = User::query()->firstOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'job_title' => $account['job_title'],
                    'password' => 'password',
                    'status' => 'active',
                    'email_verified_at' => now(),
                ],
            );

            $user->syncRoles($account['roles']);
        }
    }
}
