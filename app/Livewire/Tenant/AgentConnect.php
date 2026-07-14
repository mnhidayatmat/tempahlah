<?php

namespace App\Livewire\Tenant;

use App\Models\AgentConversation;
use App\Models\AgentLearnedFaq;
use App\Models\AgentUsageDaily;
use App\Models\Tenant;
use App\Models\TenantIntegration;
use App\Models\WhatsappSession;
use App\Services\Agent\AgentService;
use App\Services\Agent\AgentSettings;
use App\Services\Agent\ConversationLearner;
use App\Services\Agent\TrainingQaGenerator;
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

    /**
     * Training Q&A — the agent's editable FAQ knowledge base.
     * Each row: ['q' => ..., 'a' => ..., 'source' => 'auto'|'custom'].
     * Seeded from live tenant info; the owner can tune, add or delete.
     *
     * @var array<int, array{q:string, a:string, source:string}>
     */
    public array $trainingQa = [];
    public bool $trainingQaSeeded = false;

    /**
     * Learned-suggestion review — the answer the host is about to approve for
     * each pending AgentLearnedFaq, keyed by row id. Pre-filled from the
     * suggested answer; editable (a 'gap' starts blank and needs one typed).
     *
     * @var array<int, string>
     */
    public array $learnedDraft = [];

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
        $this->trainingQaSeeded   = $s->trainingQaSeeded;

        // Seed the FAQ from the tenant's live info the first time (or if it's
        // still empty and was never explicitly cleared) so a host always has a
        // useful default set to refine. Persisted once they hit Save.
        if (! empty($s->trainingQa)) {
            $this->trainingQa = $s->trainingQa;
        } elseif (! $s->trainingQaSeeded) {
            $this->trainingQa = app(TrainingQaGenerator::class)->generate($tenant);
        }

        if ($s->businessHours) {
            $this->useBusinessHours = true;
            $day = $s->businessHours['days']['mon'] ?? null;
            if ($day) {
                $this->businessHoursStart = $day[0] ?? '09:00';
                $this->businessHoursEnd   = $day[1] ?? '21:00';
            }
        }

        $this->seedLearnedDrafts();
    }

    /** Pre-fill the editable answer box for every pending suggestion. */
    protected function seedLearnedDrafts(): void
    {
        foreach ($this->learnedSuggestions() as $s) {
            if (! array_key_exists($s->id, $this->learnedDraft)) {
                $this->learnedDraft[$s->id] = (string) ($s->suggested_answer ?? '');
            }
        }
    }

    /**
     * Pending suggestions the agent distilled from real conversations, newest
     * first. Recurring (answered) ones lead; gaps ("needs your answer") follow.
     *
     * @return \Illuminate\Support\Collection<int, AgentLearnedFaq>
     */
    #[Computed]
    public function learnedSuggestions()
    {
        if (! $this->tenantId) {
            return collect();
        }

        return AgentLearnedFaq::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $this->tenantId)
            ->where('status', AgentLearnedFaq::STATUS_PENDING)
            ->orderByRaw("CASE WHEN kind = 'recurring' THEN 0 ELSE 1 END")
            ->orderByDesc('id')
            ->limit(30)
            ->get();
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

    /**
     * Master switch lives OUTSIDE the settings <form>, so its `wire:model.live`
     * change must persist on its own — otherwise the UI flips to "Off" but
     * `tenant_integrations.config.enabled` stays true and the agent keeps
     * replying to customers.
     */
    public function updatedEnabled(bool $value): void
    {
        if (! $this->tenantId) return;

        $row = TenantIntegration::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantId)
            ->where('provider', 'agent')
            ->first();

        if (! $row) {
            $row = new TenantIntegration([
                'tenant_id' => $this->tenantId,
                'provider'  => 'agent',
            ]);
            $row->config = ['enabled' => $value];
        } else {
            $cfg = is_array($row->config) ? $row->config : [];
            $cfg['enabled'] = $value;
            $row->config = $cfg;
        }

        $row->enabled = $value;
        if ($value && ! $row->connected_at) {
            $row->connected_at = now();
        }
        $row->save();

        $this->flashOk($value ? __('AI agent turned on.') : __('AI agent turned off.'));
    }

    /**
     * When the owner edits a seeded ('auto') pair, promote it to 'custom' so a
     * later "Regenerate from my info" never overwrites their refinement.
     * Livewire passes the dotted key, e.g. "trainingQa.2.a".
     */
    public function updatedTrainingQa(mixed $value, ?string $key = null): void
    {
        if (! $key) return;
        $idx = (int) explode('.', $key)[0];
        if (isset($this->trainingQa[$idx]) && ($this->trainingQa[$idx]['source'] ?? '') === 'auto') {
            $this->trainingQa[$idx]['source'] = 'custom';
        }
    }

    public function addQa(): void
    {
        if (count($this->trainingQa) >= TrainingQaGenerator::MAX_PAIRS) {
            $this->flashErr(__('You can add up to :n questions.', ['n' => TrainingQaGenerator::MAX_PAIRS]));
            return;
        }
        $this->trainingQa[] = ['q' => '', 'a' => '', 'source' => 'custom'];
    }

    public function removeQa(int $index): void
    {
        if (isset($this->trainingQa[$index])) {
            unset($this->trainingQa[$index]);
            $this->trainingQa = array_values($this->trainingQa);
        }
    }

    /**
     * Rebuild the auto pairs from the tenant's current info while KEEPING every
     * question the owner has hand-written or refined (source='custom').
     */
    public function regenerateQa(TrainingQaGenerator $generator): void
    {
        $tenant = $this->tenant();
        if (! $tenant) return;

        $custom = array_values(array_filter(
            $this->trainingQa,
            fn ($p) => ($p['source'] ?? '') === 'custom',
        ));

        $this->trainingQa = array_merge($generator->generate($tenant), $custom);
        $this->flashOk(__('Refreshed the default answers from your latest info. Your edited questions were kept.'));
    }

    /**
     * Approve a learned suggestion → it becomes a real FAQ answer the agent
     * uses. The answer is whatever the host has in the edit box (required —
     * a 'gap' must be given an answer). Added as source='custom' so a later
     * "Regenerate from my info" never wipes it, and persisted immediately.
     */
    public function approveLearned(int $id): void
    {
        $row = AgentLearnedFaq::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $this->tenantId)
            ->where('status', AgentLearnedFaq::STATUS_PENDING)
            ->find($id);
        if (! $row) return;

        $answer = trim((string) ($this->learnedDraft[$id] ?? $row->suggested_answer ?? ''));
        if ($answer === '') {
            $this->flashErr(__('Type an answer before approving this one.'));
            return;
        }

        if (count($this->trainingQa) >= TrainingQaGenerator::MAX_PAIRS) {
            $this->flashErr(__('Your FAQ is full (:n answers). Remove one before approving more.', ['n' => TrainingQaGenerator::MAX_PAIRS]));
            return;
        }

        $this->trainingQa[] = ['q' => trim($row->question), 'a' => $answer, 'source' => 'custom'];
        $this->persistTrainingQaConfig();

        $row->update(['status' => AgentLearnedFaq::STATUS_APPROVED, 'reviewed_at' => now()]);
        unset($this->learnedDraft[$id]);
        unset($this->learnedSuggestions); // bust the computed cache

        $this->flashOk(__('Added to your agent\'s answers. It\'ll use this from now on.'));
    }

    /** Dismiss a suggestion — it won't be shown again. */
    public function dismissLearned(int $id): void
    {
        $row = AgentLearnedFaq::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $this->tenantId)
            ->where('status', AgentLearnedFaq::STATUS_PENDING)
            ->find($id);
        if (! $row) return;

        $row->update(['status' => AgentLearnedFaq::STATUS_DISMISSED, 'reviewed_at' => now()]);
        unset($this->learnedDraft[$id]);
        unset($this->learnedSuggestions);

        $this->flashOk(__('Dismissed.'));
    }

    /**
     * Manually scan recent conversations now (the scheduled job runs weekly).
     * Runs synchronously — one bounded LLM call — and surfaces fresh
     * suggestions for review.
     */
    public function scanNow(ConversationLearner $learner): void
    {
        $tenant = $this->tenant();
        if (! $tenant) return;

        if (! $this->unlocked()) {
            $this->flashErr(__('Upgrade to use the AI agent.'));
            return;
        }

        try {
            $new = $learner->learn($tenant);
        } catch (\Throwable $e) {
            $this->flashErr(__('Scan failed: :err', ['err' => $e->getMessage()]));
            return;
        }

        unset($this->learnedSuggestions);
        $this->seedLearnedDrafts();

        $this->flashOk($new > 0
            ? __(':n new suggestion(s) from your recent chats — review them below.', ['n' => $new])
            : __('No new suggestions right now. The agent will keep watching your conversations.'));
    }

    /**
     * Persist ONLY the training_qa slice of the agent config (leaving every
     * other setting untouched), used when approving a suggestion out-of-band
     * from the main Save button.
     */
    protected function persistTrainingQaConfig(): void
    {
        $this->trainingQa = array_values($this->trainingQa);

        $row = TenantIntegration::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantId)
            ->where('provider', 'agent')
            ->first();

        if (! $row) {
            $row = new TenantIntegration(['tenant_id' => $this->tenantId, 'provider' => 'agent']);
            $row->config = [];
        }

        $cfg = is_array($row->config) ? $row->config : [];
        $cfg['training_qa'] = $this->trainingQa;
        $cfg['training_qa_seeded'] = true;
        $row->config = $cfg;
        $row->save();

        $this->trainingQaSeeded = true;
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
            'trainingQa'         => 'array|max:'.TrainingQaGenerator::MAX_PAIRS,
            'trainingQa.*.q'     => 'nullable|string|max:400',
            'trainingQa.*.a'     => 'nullable|string|max:2000',
        ]);

        // Drop blank rows; keep only complete Q&A pairs.
        $trainingQa = array_values(array_filter(array_map(function ($p) {
            $q = trim((string) ($p['q'] ?? ''));
            $a = trim((string) ($p['a'] ?? ''));
            if ($q === '' || $a === '') return null;
            return [
                'q'      => $q,
                'a'      => $a,
                'source' => ($p['source'] ?? '') === 'custom' ? 'custom' : 'auto',
            ];
        }, $this->trainingQa)));
        $this->trainingQa = $trainingQa;

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
            'training_qa'         => $trainingQa,
            'training_qa_seeded'  => true,
        ];
        $this->trainingQaSeeded = true;

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
