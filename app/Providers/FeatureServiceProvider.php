<?php

namespace App\Providers;

use App\Models\Tenant;
use Illuminate\Support\ServiceProvider;
use Laravel\Pennant\Feature;

class FeatureServiceProvider extends ServiceProvider
{
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
        Feature::define('paid_tier', fn (Tenant $tenant) => $tenant->isPaid());

        Feature::define('multiple_properties', fn (Tenant $tenant) => $tenant->isPaid());

        Feature::define('toyyibpay_payment', fn (Tenant $tenant) => $tenant->isPaid());

        Feature::define('auto_reminders', fn (Tenant $tenant) => $tenant->isPaid());

        Feature::define('whatsapp_business', fn (Tenant $tenant) => $tenant->isPaid());

        Feature::define('custom_domain', fn (Tenant $tenant) => $tenant->isPaid());

        Feature::define('tenant_branded_emails', fn (Tenant $tenant) => $tenant->isPaid());

        Feature::define('custom_invoice_template', fn (Tenant $tenant) => $tenant->isPaid());

        Feature::define('marketplace_listing', fn (Tenant $tenant) => $tenant->isPaid());

        Feature::define('dynamic_pricing', fn (Tenant $tenant) => $tenant->isPaid());

        Feature::define('staff_accounts', fn (Tenant $tenant) => $tenant->isPaid() ? 5 : 1);

        Feature::define('reports_history_days', fn (Tenant $tenant) => $tenant->isPaid() ? null : 30);

        Feature::define('export_reports', fn (Tenant $tenant) => $tenant->isPaid());

        Feature::define('api_access', fn (Tenant $tenant) => $tenant->isPaid());

        Feature::define('two_way_calendar_sync', fn (Tenant $tenant) => $tenant->isPaid());

        Feature::define('ical_channel_sync', fn (Tenant $tenant) => $tenant->isPaid());

        Feature::define('auto_operational_tasks', fn (Tenant $tenant) => $tenant->isPaid());

        Feature::define('inventory_alerts', fn (Tenant $tenant) => $tenant->isPaid());

        Feature::define('refund_handling', fn (Tenant $tenant) => $tenant->isPaid());

        Feature::define('ai_agent', fn (Tenant $tenant) => $tenant->isPaid());
    }
}
