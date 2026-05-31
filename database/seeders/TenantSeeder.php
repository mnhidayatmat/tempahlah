<?php

namespace Database\Seeders;

use App\Actions\Tenancy\CreateTenantAndOwner;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class TenantSeeder extends Seeder
{
    public function run(): void
    {
        if (Tenant::where('slug', 'demo-homestay')->exists()) {
            return;
        }

        app(CreateTenantAndOwner::class)->execute([
            'name' => 'Demo Owner',
            'email' => 'owner@demo.test',
            'phone' => '+60123456789',
            'password' => 'password',
            'business_name' => 'Demo Homestay',
            'locale' => 'ms',
        ]);
    }
}
