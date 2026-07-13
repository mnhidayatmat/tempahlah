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
                    // Email verification is not part of the v1 onboarding flow
                    // (no verify route exists, and prod SES is in sandbox so a
                    // verification email could not be delivered to a new host
                    // anyway). Mark the owner verified on creation so nothing
                    // gates on an unverifiable state.
                    'email_verified_at' => now(),
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
        'tempahlah', 'homestaymy', 'homestay', 'about', 'contact', 'pricing', 'login',
        'register', 'onboard', 'auth', 'oauth', 'webhook', 'webhooks',
        'status', 'health', 'localhost', 'ftp', 'smtp', 'imap', 'pop',
    ];

    /** Public accessor so other code (e.g. SettingsController validation) can reuse the same list. */
    public static function reservedSlugs(): array
    {
        return self::RESERVED_SLUGS;
    }

    protected function uniqueSlug(string $businessName): string
    {
        $base = Str::slug($businessName) ?: 'tenant';
        $slug = in_array($base, self::RESERVED_SLUGS, true) ? $base.'-1' : $base;
        $i = 0;

        // withTrashed: the tenants.slug unique index still counts soft-deleted
        // rows, so a slug held by a deleted tenant must be treated as taken —
        // otherwise the INSERT here hits a duplicate-key error (500).
        while (Tenant::withTrashed()->where('slug', $slug)->exists() || in_array($slug, self::RESERVED_SLUGS, true)) {
            $i++;
            $slug = $base.'-'.$i;
        }

        return $slug;
    }
}
