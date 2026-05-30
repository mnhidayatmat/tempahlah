<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Payments\Toyyibpay\ToyyibpayClient;
use App\Services\Payments\Toyyibpay\ToyyibpayException;
use App\Services\Payments\Toyyibpay\ToyyibpayLog;
use Illuminate\Console\Command;

/**
 * php artisan toyyibpay:probe {tenant}
 *
 * Verifies a tenant's Toyyibpay integration end-to-end against the
 * (sandbox or production) API. Useful during onboarding and as a
 * post-deploy smoke test.
 *
 * Examples:
 *   php artisan toyyibpay:probe 1
 *   php artisan toyyibpay:probe demo-homestay
 */
class ToyyibpayProbe extends Command
{
    protected $signature = 'toyyibpay:probe
                            {tenant : Tenant ID or slug}
                            {--no-log : Skip writing the payment_transactions audit row}';

    protected $description = 'Probe a tenant\'s Toyyibpay credentials by creating a minimal test bill.';

    public function handle(): int
    {
        $arg = $this->argument('tenant');
        $tenant = is_numeric($arg)
            ? Tenant::withoutGlobalScopes()->find((int) $arg)
            : Tenant::withoutGlobalScopes()->where('slug', $arg)->first();

        if (! $tenant) {
            $this->error("Tenant not found: {$arg}");
            return self::FAILURE;
        }

        $this->info("Probing Toyyibpay for tenant: {$tenant->business_name} (id={$tenant->id}, slug={$tenant->slug})");

        try {
            $client = ToyyibpayClient::forTenant($tenant->id);
        } catch (ToyyibpayException $e) {
            $this->error($e->getMessage());
            $this->line('  → Tenant must save valid credentials at /dashboard/integrations/toyyibpay first.');
            return self::FAILURE;
        }

        $this->line('  Environment:    '.($client->sandbox ? 'SANDBOX (dev.toyyibpay.com)' : 'PRODUCTION (toyyibpay.com)'));
        $this->line('  Base URL:       '.$client->baseUrl);
        $this->line('  Category code:  '.$client->categoryCode);
        $this->line('  Secret prefix:  '.substr($client->secretKey, 0, 6).'…');

        $this->newLine();
        $this->line('→ Calling createBill (RM 1.00 ping)…');

        $result = $client->testConnection();

        if (! $this->option('no-log')) {
            ToyyibpayLog::recordApiCall(
                $tenant->id, null, 'testConnection.cli',
                ['sandbox' => $client->sandbox],
                ['bill_code' => $result['bill_code'], 'raw_body' => $result['raw_body']],
                $result['http_status'], $result['ok'],
                $result['ok'] ? null : substr($result['raw_body'], 0, 200),
            );
        }

        $this->newLine();
        if ($result['ok']) {
            $this->info('✓ SUCCESS');
            $this->line('  HTTP:     '.$result['http_status']);
            $this->line('  BillCode: '.$result['bill_code']);
            $this->line('  URL:      '.$result['payment_url']);
            $this->newLine();
            $this->line("Open the URL above in a browser to confirm Toyyibpay renders the bill (don't actually pay it).");
            return self::SUCCESS;
        }

        $this->error('✗ FAILURE');
        $this->line('  HTTP: '.$result['http_status']);
        $this->line('  Body: '.substr($result['raw_body'], 0, 500));
        $this->newLine();
        $this->line('Common causes:');
        $this->line('  • Wrong User Secret Key (Toyyibpay → Profile)');
        $this->line('  • Wrong Category Code (Toyyibpay → Categories)');
        $this->line('  • Sandbox toggle wrong (sandbox creds used on production endpoint or vice-versa)');
        return self::FAILURE;
    }
}
