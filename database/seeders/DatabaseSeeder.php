<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            SystemDefaultsSeeder::class,
        ]);

        if (! app()->environment(['local', 'testing'])) {
            $this->command?->info('Datos base preparados. Crea el administrador con: php artisan signage:admin');

            return;
        }

        $this->call([
            DemoUserSeeder::class,
            DemoDataSeeder::class,
            BusinessDemoSeeder::class,
        ]);
    }
}
