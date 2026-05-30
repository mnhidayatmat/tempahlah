<?php

namespace Database\Seeders;

use App\Models\SuperAdmin;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleAndPermissionSeeder::class,
            AmenitySeeder::class,
            TenantSeeder::class,
        ]);

        SuperAdmin::firstOrCreate(
            ['email' => 'admin@tempahlah.com'],
            [
                'name' => 'Platform Admin',
                'password' => 'ChangeMe123!',
                'email_verified_at' => now(),
            ],
        );
    }
}
