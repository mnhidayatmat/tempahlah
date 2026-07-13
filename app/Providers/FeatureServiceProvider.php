<?php

namespace App\Providers;

use App\Models\Tenant;
use App\Support\Billing\Plans;
use Illuminate\Support\ServiceProvider;
use Laravel\Pennant\Feature;

class FeatureServiceProvider extends ServiceProvider
{
    /**
     * One Pennant flag per plan feature key. Which plans hold which key lives
     * in config/homestay.php → plans (additive up the ladder); each flag here
     * resolves through Tenant::hasFeature(), so a plan change flips every
     * flag at once (SubscriptionObserver purges the tenant's cached rows).
     */
    protected const PLAN_FEATURE_FLAGS = [
        'multiple_properties',
        'payment_gateway',       // Online gateways (SecurePay/Toyyibpay/Billplz).
                                 // Free = manual payments only, enforced at the
                                 // choke point CreateGatewayBill::resolveProvider().
        'invoice_documents',     // Invoice/receipt records + branded PDF + send actions.
        'auto_reminders',
        'whatsapp_business',
        'tenant_branded_emails',
        'brand_theme',           // Dashboard + public-page colour palette.
        'custom_invoice_template',
        'marketplace_listing',
        'marketplace_priority',  // Pro+: listings rank above standard in search.
        'marketplace_featured',  // Ultra: top / featured placement.
        'dynamic_pricing',
        'reports',               // Reports dashboard incl. PDF export.
        'advanced_reports',      // Ultra: multi-property consolidated reports.
        'export_reports',
        'api_access',
        'two_way_calendar_sync',
        'ical_channel_sync',     // Airbnb + Booking.com iCal.
        'auto_operational_tasks',
        'inventory_alerts',
        'refund_handling',
        'ai_agent',
        'subdomain_booking_page', // {slug}.tempahlah.com; free = apex path URL.
        'white_label',           // Ultra: no "Powered by Tempahlah" on public pages/invoices.
        'dedicated_support',
    ];

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Feature::resolveScopeUsing(function ($driver) {
            return app(\App\Support\Tenancy\TenantContext::class)->current();
        });

        $this->defineTenantFeatures();
    }

    protected function defineTenantFeatures(): void
    {
        foreach (self::PLAN_FEATURE_FLAGS as $flag) {
            Feature::define($flag, fn (Tenant $tenant) => $tenant->hasFeature($flag));
        }

        // Backward-compatible alias: "any paid tier" (pro or ultra).
        Feature::define('paid_tier', fn (Tenant $tenant) => $tenant->isPaid());

        // Superseded by 'payment_gateway' — predates Billplz + SecurePay and was
        // never enforced anywhere. Kept so any stored flag row stays resolvable.
        Feature::define('toyyibpay_payment', fn (Tenant $tenant) => $tenant->hasFeature('payment_gateway'));

        // Retired: custom domains are not offered on any tier of the 3-tier
        // model. The flag stays defined so stored rows resolve, always off.
        Feature::define('custom_domain', fn () => false);

        // Numeric flags read straight from the plan limits (null = unlimited).
        Feature::define('staff_accounts', fn (Tenant $tenant) => Plans::limit($tenant->planKey(), 'staff'));

        Feature::define('reports_history_days', fn (Tenant $tenant) => $tenant->hasFeature('reports') ? null : 30);
    }
}
