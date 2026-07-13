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
        $tenant = DB::transaction(function () use ($data) {
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

            $this->attributeAffiliate($tenant, $user);

            return $tenant->fresh(['subscription', 'owner']);
        });

        // Fired after the transaction commits (not from inside it) so the
        // queue worker never races a not-yet-committed tenant row. Sends the
        // day-0 onboarding email right away instead of waiting for the next
        // daily batch — see SendOnboardingWelcomeEmail for the fallback story
        // if this particular send fails.
        \App\Jobs\SendOnboardingWelcomeEmail::dispatch($tenant->id);

        return $tenant;
    }

    /**
     * Affiliate attribution: if the signup carried a referral cookie
     * (tph_ref, set by an affiliate link), bind this tenant to that affiliate
     * — permanently (affiliate_referrals.tenant_id is unique). Best-effort:
     * a failure here must never break a signup. Self-referrals (an affiliate
     * signing up their own new workspace) are skipped.
     */
    protected function attributeAffiliate(Tenant $tenant, User $user): void
    {
        try {
            // In console/seeder contexts request() is an empty Request → no
            // cookie → no-op, so no explicit console guard is needed.
            $code = \App\Support\Affiliate\ReferralAttribution::code(request());

            if (! $code) {
                return;
            }

            $affiliate = \App\Models\Affiliate::query()
                ->where('code', $code)
                ->where('status', \App\Models\Affiliate::STATUS_ACTIVE)
                ->first();

            if (! $affiliate) {
                return;
            }

            // Self-referral guard: referring yourself earns nothing.
            if ((int) $affiliate->user_id === (int) $user->id
                || ($affiliate->email && strcasecmp($affiliate->email, (string) $user->email) === 0)) {
                return;
            }

            \App\Models\AffiliateReferral::query()->firstOrCreate(
                ['tenant_id' => $tenant->id],
                ['affiliate_id' => $affiliate->id],
            );

            \App\Support\Affiliate\ReferralAttribution::clear();
        } catch (\Throwable $e) {
            report($e);
        }
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
