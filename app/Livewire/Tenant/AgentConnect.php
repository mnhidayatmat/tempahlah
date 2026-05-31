<?php

namespace App\Livewire\Tenant;

use App\Models\AgentConversation;
use App\Models\AgentUsageDaily;
use App\Models\Tenant;
use App\Models\TenantIntegration;
use App\Models\WhatsappSession;
use App\Services\Agent\AgentService;
use App\Services\Agent\AgentSettings;
use App\Services\Agent\Llm\LlmClientFactory;
use App\Support\Tenancy\TenantContext;
use Laravel\Pennant\Feature;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Tenant-facing AI agent settings panel.
 *
 * Lives at /dashboard/integrations/agent. Polls every 5s for the live
 * conversation list.
 */
class AgentConnect extends Component
{
    public ?int $tenantId = null;

    // Settings form fields (one-to-one with AgentSettings).
    public bool $enabled = false;
    public string $llmProvider = '';
    public string $llmModel = '';
    public string $persona = 'friendly';
    public string $greetingBm = '';
    public string $greetingEn = '';
    public string $signature = '';
    public string $outOfHoursBm = '';
    public string $outOfHoursEn = '';
    public string $escalationKeywords = '';
    public ?string $handoffPhone = null;
    public int $dailyCap = 200;
    public string $customKnowledge = '';
    public bool $sendPhotosEnabled = true;
    public string $replyLanguages = 'auto';
    public bool $useBusinessHours = false;
    public string $businessHoursStart = '09:00';
    public string $businessHoursEnd = '21:00';

    // Playground.
    public string $testMessage = '';
    public string $testReply = '';
    public bool $testRunning = false;

    // Flash.
    public ?string $flash = null;
    public ?string $flashKind = null;

    public function mount(): void
    {
        $tenant = app(TenantContext::class)->current();
        $this->tenantId = $tenant?->id;
        if (! $tenant) return;

        $s = AgentSettings::forTenant($tenant);
        $this->enabled            = $s->enabled;
        $this->llmProvider        = $s->llmProvider;
        $this->llmModel           = $s->llmModel;
        $this->persona            = $s->persona;
        $this->greetingBm         = $s->greetingBm;
        $this->greetingEn         = $s->greetingEn;
        $this->signature          = $s->signature;
        $this->outOfHoursBm       = $s->outOfHoursBm;
        $this->outOfHoursEn       = $s->outOfHoursEn;
        $this->escalationKeywords = implode(', ', $s->escalationKeywords);
        $this->handoffPhone       = $s->handoffPhone;
        $this->dailyCap           = $s->dailyCap;
        $this->customKnowledge    = $s->customKnowledge;
        $this->sendPhotosEnabled  = $s->sendPhotosEnabled;
        $this->replyLanguages     = $s->replyLanguages;

        if ($s->businessHours) {
            $this->useBusinessHours = true;
            $day = $s->businessHours['days']['mon'] ?? null;
            if ($day) {
                $this->businessHoursStart = $day[0] ?? '09:00';
                $this->businessHoursEnd   = $day[1] ?? '21:00';
            }
        }
    }

    #[Computed]
    public function tenant(): ?Tenant
    {
        return app(TenantContext::class)->current();
    }

    #[Computed]
    public function unlocked(): bool
    {
        $t = $this->tenant();
        return $t ? (bool) Feature::for($t)->active('ai_agent') : false;
    }

    #[Computed]
    public function whatsappConnected(): bool
    {
        $s = WhatsappSession::withoutGlobalScopes()->where('tenant_id', $this->tenantId)->first();
        return $s?->isConnected() ?? false;
    }

    /** @return array<string, array{label:string, models:array}> */
    #[Computed]
    public function availableProviders(): array
    {
        return app(LlmClientFactory::class)->availableProviders();
    }

    #[Computed]
    public function modelsForProvider(): array
    {
        $providers = $this->availableProviders();
        return $providers[$this->llmProvider]['models'] ?? [];
    }

    #[Computed]
    public function usageToday(): array
    {
        $row = AgentUsageDaily::todayFor($this->tenantId);
        return [
            'replies'  => (int) $row->reply_count,
            'inbounds' => (int) $row->inbound_count,
            'tools'    => (int) $row->tool_calls,
            'cap'      => $this->dailyCap,
        ];
    }

    #[Computed]
    public function recentConversations()
    {
        return AgentConversation::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $this->tenantId)
            ->orderByDesc('last_inbound_at')
            ->limit(8)
            ->get();
    }

    public function save(): void
    {
        $this->validate([
            'llmProvider' => 'required|string|max:32',
            'llmModel'    => 'required|string|max:120',
            'persona'     => 'required|in:friendly,formal,concise',
            'greetingBm'  => 'nullable|string|max:600',
            'greetingEn'  => 'nullable|string|max:600',
            'signature'   => 'nullable|string|max:200',
            'outOfHoursBm'=> 'nullable|string|max:400',
            'outOfHoursEn'=> 'nullable|string|max:400',
            'handoffPhone'=> 'nullable|string|max:24',
            'dailyCap'    => 'required|integer|min:1|max:'.config('agent.platform_max_cap'),
            'customKnowledge' => 'nullable|string|max:4000',
            'replyLanguages'  => 'required|in:auto,ms,en',
            'businessHoursStart' => 'required|date_format:H:i',
            'businessHoursEnd'   => 'required|date_format:H:i',
        ]);

        $businessHours = null;
        if ($this->useBusinessHours) {
            $window = [$this->businessHoursStart, $this->businessHoursEnd];
            $businessHours = [
                'tz' => 'Asia/Kuala_Lumpur',
                'days' => array_fill_keys(['mon','tue','wed','thu','fri','sat','sun'], $window),
            ];
        }

        $keywords = array_values(array_filter(array_map(
            fn ($k) => trim($k),
            explode(',', $this->escalationKeywords),
        )));

        $config = [
            'enabled'             => $this->enabled,
            'llm_provider'        => $this->llmProvider,
            'llm_model'           => $this->llmModel,
            'persona'             => $this->persona,
            'greeting_bm'         => $this->greetingBm,
            'greeting_en'         => $this->greetingEn,
            'signature'           => $this->signature,
            'business_hours'      => $businessHours,
            'out_of_hours_bm'     => $this->outOfHoursBm,
            'out_of_hours_en'     => $this->outOfHoursEn,
            'escalation_keywords' => $keywords,
            'handoff_phone'       => $this->handoffPhone,
            'daily_cap'           => $this->dailyCap,
            'custom_knowledge'    => $this->customKnowledge,
            'send_photos_enabled' => $this->sendPhotosEnabled,
            'reply_languages'     => $this->replyLanguages,
        ];

        $row = TenantIntegration::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantId)
            ->where('provider', 'agent')
            ->first();

        if (! $row) {
            $row = new TenantIntegration([
                'tenant_id' => $this->tenantId,
                'provider'  => 'agent',
            ]);
        }
        $row->enabled = $this->enabled;
        $row->config = $config;
        if (! $row->connected_at && $this->enabled) {
            $row->connected_at = now();
        }
        $row->save();

        $this->flashOk(__('AI agent settings saved.'));
    }

    public function runTest(AgentService $agent): void
    {
        $this->validate(['testMessage' => 'required|string|max:600']);
        $this->testRunning = true;

        try {
            $tenant = $this->tenant();
            // Use a phantom playground conversation row so the agent
            // has a target without mutating real conversations.
            $convo = AgentConversation::firstOrCreate(
                ['tenant_id' => $this->tenantId, 'guest_phone' => '+playground'],
                ['status' => AgentConversation::STATUS_ACTIVE],
            );
            $convo->update(['last_inbound_at' => now()]);

            $this->testReply = $agent->dryRun($tenant, $convo, $this->testMessage);
        } catch (\Throwable $e) {
            $this->testReply = '';
            $this->flashErr(__('Test failed: :err', ['err' => $e->getMessage()]));
        } finally {
            $this->testRunning = false;
        }
    }

    public function takeOver(int $conversationId): void
    {
        $convo = AgentConversation::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantId)
            ->where('id', $conversationId)
            ->first();
        if ($convo) {
            $convo->update([
                'status' => AgentConversation::STATUS_ESCALATED,
                'escalated_at' => now(),
                'escalation_reason' => 'manual takeover by owner',
            ]);
            $this->flashOk(__('Conversation taken over. The AI will stay silent on this thread.'));
        }
    }

    public function reactivate(int $conversationId): void
    {
        $convo = AgentConversation::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantId)
            ->where('id', $conversationId)
            ->first();
        if ($convo) {
            $convo->update([
                'status' => AgentConversation::STATUS_ACTIVE,
                'escalated_at' => null,
                'escalation_reason' => null,
            ]);
            $this->flashOk(__('AI agent re-activated for this conversation.'));
        }
    }

    public function refresh(): void { /* no-op for wire:poll */ }

    public function render()
    {
        return view('livewire.tenant.agent-connect');
    }

    protected function flashOk(string $msg): void
    {
        $this->flash = $msg;
        $this->flashKind = 'ok';
    }

    protected function flashErr(string $msg): void
    {
        $this->flash = $msg;
        $this->flashKind = 'err';
    }
}
