<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\TenantIntegration;
use App\Services\Agent\ConversationLearner;
use Illuminate\Console\Command;
use Laravel\Pennant\Feature;

/**
 * Weekly: for every tenant whose AI agent is on (and on a plan that includes
 * it), distil recent WhatsApp conversations into pending FAQ suggestions the
 * host can review. Suggestions are never auto-applied — see ConversationLearner.
 */
class DistillAgentLearnings extends Command
{
    protected $signature = 'agent:distill-learnings
        {--tenant= : Only run for this tenant id}
        {--dry-run : Report eligible tenants without calling the LLM}';

    protected $description = 'Distil recent AI-agent conversations into pending FAQ suggestions for host review.';

    public function handle(ConversationLearner $learner): int
    {
        if (! config('agent.learning.enabled', true)) {
            $this->info('Agent learning is disabled (agent.learning.enabled=false).');
            return self::SUCCESS;
        }

        $tenants = $this->eligibleTenants();
        if ($tenants->isEmpty()) {
            $this->info('No tenants with the agent enabled.');
            return self::SUCCESS;
        }

        $dry = (bool) $this->option('dry-run');
        $totalNew = 0;

        foreach ($tenants as $tenant) {
            // Plan gate — the agent (and its learning) is a paid feature.
            if (! Feature::for($tenant)->active('ai_agent')) {
                continue;
            }

            if ($dry) {
                $this->line("would scan: [{$tenant->id}] {$tenant->business_name}");
                continue;
            }

            try {
                $new = $learner->learn($tenant);
                $totalNew += $new;
                if ($new > 0) {
                    $this->line("[{$tenant->id}] {$tenant->business_name}: +{$new} suggestion(s)");
                }
            } catch (\Throwable $e) {
                $this->error("[{$tenant->id}] {$tenant->business_name}: {$e->getMessage()}");
            }
        }

        $this->info($dry ? 'Dry run complete.' : "Done. {$totalNew} new suggestion(s) across all tenants.");
        return self::SUCCESS;
    }

    /** @return \Illuminate\Support\Collection<int, Tenant> */
    protected function eligibleTenants()
    {
        $query = Tenant::query()->withoutGlobalScopes();

        if ($id = $this->option('tenant')) {
            return $query->whereKey((int) $id)->get();
        }

        $tenantIds = TenantIntegration::query()
            ->withoutGlobalScopes()
            ->where('provider', 'agent')
            ->where('enabled', true)
            ->pluck('tenant_id')
            ->unique();

        return $query->whereIn('id', $tenantIds)->get();
    }
}
