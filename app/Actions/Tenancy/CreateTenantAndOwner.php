<?php

namespace App\Actions\Tenancy;

use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateTenantAndOwner
{
    public function execute(array $data): Tenant
    {
        return DB::transaction(function () use ($data) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'phone' => $data['phone'] ?? null,
                    'password' => $data['password'],
                    'locale' => $data['locale'] ?? 'ms',
                    'user_type' => User::TYPE_TENANT_USER,
                ],
            );

            $slug = $this->uniqueSlug($data['business_name']);

            $tenant = Tenant::create([
                'slug' => $slug,
                'business_name' => $data['business_name'],
                'business_email' => $data['email'],
                'business_phone' => $data['phone'] ?? null,
                'owner_user_id' => $user->id,
                'kyc_status' => 'pending',
                'status' => 'active',
                'default_locale' => $data['locale'] ?? 'ms',
            ]);

            Subscription::create([
                'tenant_id' => $tenant->id,
                'plan' => Subscription::PLAN_FREE,
                'status' => Subscription::STATUS_ACTIVE,
                'billing_method' => 'manual',
                'monthly_amount' => 0,
                'currency' => 'MYR',
                'current_period_start' => now(),
                'current_period_end' => now()->addYear(),
            ]);

            TenantUser::create([
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'role' => TenantUser::ROLE_OWNER,
                'status' => 'active',
                'invited_at' => now(),
                'joined_at' => now(),
            ]);

            $user->assignRole('owner');

            return $tenant->fresh(['subscription', 'owner']);
        });
    }

    protected const RESERVED_SLUGS = [
        'www', 'mail', 'api', 'app', 'admin', 'super-admin', 'superadmin',
        'marketplace', 'dashboard', 'support', 'help', 'blog', 'docs',
        'dev', 'staging', 'test', 'cdn', 'static', 'assets', 'media',
        'homestaymy', 'homestay', 'about', 'contact', 'pricing', 'login',
        'register', 'onboard', 'auth', 'oauth', 'webhook', 'webhooks',
        'status', 'health', 'localhost', 'ftp', 'smtp', 'imap', 'pop',
    ];

    protected function uniqueSlug(string $businessName): string
    {
        $base = Str::slug($businessName) ?: 'tenant';
        $slug = in_array($base, self::RESERVED_SLUGS, true) ? $base.'-1' : $base;
        $i = 0;

        while (Tenant::where('slug', $slug)->exists() || in_array($slug, self::RESERVED_SLUGS, true)) {
            $i++;
            $slug = $base.'-'.$i;
        }

        return $slug;
    }
}
