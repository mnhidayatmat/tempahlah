<?php

namespace App\Services\Agent;

use App\Models\Tenant;
use App\Models\TenantIntegration;

/**
 * Typed reader over the tenant_integrations.config blob (provider='agent').
 *
 * Always falls back to platform defaults from config/agent.php — so the
 * agent has a sane shape even before the tenant first saves settings.
 */
class AgentSettings
{
    public bool $enabled;
    public string $llmProvider;
    public string $llmModel;
    public string $persona;
    public string $greetingBm;
    public string $greetingEn;
    public string $signature;
    public ?array $businessHours;
    public string $outOfHoursBm;
    public string $outOfHoursEn;
    /** @var string[] */
    public array $escalationKeywords;
    public ?string $handoffPhone;
    public int $dailyCap;
    public string $customKnowledge;
    public bool $sendPhotosEnabled;
    public string $replyLanguages;
    /** @var array<int, array{q:string, a:string, source:string}> */
    public array $trainingQa;
    public bool $trainingQaSeeded;

    public function __construct(public Tenant $tenant, array $cfg = [])
    {
        $d = config('agent.defaults');
        $cfg = array_merge($d, array_filter($cfg, fn ($v) => $v !== null));

        $this->enabled            = (bool) ($cfg['enabled'] ?? false);
        $this->llmProvider        = (string) ($cfg['llm_provider'] ?? config('agent.default_provider'));
        $this->llmModel           = (string) ($cfg['llm_model']    ?? config('agent.default_model'));
        $this->persona            = (string) $cfg['persona'];
        $this->greetingBm         = (string) $cfg['greeting_bm'];
        $this->greetingEn         = (string) $cfg['greeting_en'];
        $this->signature          = (string) $cfg['signature'];
        $this->businessHours      = $cfg['business_hours'] ?? null;
        $this->outOfHoursBm       = (string) $cfg['out_of_hours_bm'];
        $this->outOfHoursEn       = (string) $cfg['out_of_hours_en'];
        $this->escalationKeywords = array_values((array) $cfg['escalation_keywords']);
        $this->handoffPhone       = $cfg['handoff_phone'] ?? null;
        $this->dailyCap           = min(
            (int) ($cfg['daily_cap'] ?? config('agent.default_daily_cap')),
            (int) config('agent.platform_max_cap'),
        );
        $this->customKnowledge    = (string) ($cfg['custom_knowledge'] ?? '');
        $this->sendPhotosEnabled  = (bool) ($cfg['send_photos_enabled'] ?? true);
        $this->replyLanguages     = (string) ($cfg['reply_languages'] ?? 'auto');
        $this->trainingQa         = $this->normalizeQa($cfg['training_qa'] ?? []);
        $this->trainingQaSeeded   = (bool) ($cfg['training_qa_seeded'] ?? false);
    }

    /**
     * Coerce a stored training_qa blob into a clean list of {q, a, source},
     * dropping any pair missing a question or answer.
     *
     * @return array<int, array{q:string, a:string, source:string}>
     */
    private function normalizeQa(mixed $raw): array
    {
        if (! is_array($raw)) return [];

        $out = [];
        foreach ($raw as $item) {
            if (! is_array($item)) continue;
            $q = trim((string) ($item['q'] ?? ''));
            $a = trim((string) ($item['a'] ?? ''));
            if ($q === '' || $a === '') continue;
            $out[] = [
                'q'      => $q,
                'a'      => $a,
                'source' => ($item['source'] ?? '') === 'custom' ? 'custom' : 'auto',
            ];
        }
        return $out;
    }

    public static function forTenant(Tenant $tenant): self
    {
        $row = TenantIntegration::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('provider', 'agent')
            ->first();

        $cfg = $row?->config ?? [];
        return new self($tenant, is_array($cfg) ? $cfg : []);
    }

    public function withinBusinessHours(?\DateTimeInterface $when = null): bool
    {
        if (! $this->businessHours) return true;

        $when ??= now();
        $tz = $this->businessHours['tz'] ?? 'Asia/Kuala_Lumpur';
        $local = \Carbon\Carbon::instance($when)->setTimezone($tz);
        $key = strtolower($local->format('D')); // mon|tue|...
        $window = $this->businessHours['days'][$key] ?? null;
        if (! $window || count($window) !== 2) return false;

        [$start, $end] = $window;
        $cur = $local->format('H:i');
        return $cur >= $start && $cur <= $end;
    }

    /** Matches any escalation keyword in $body (case-insensitive). */
    public function detectEscalation(string $body): ?string
    {
        $haystack = mb_strtolower($body);
        foreach ($this->escalationKeywords as $kw) {
            if ($kw && str_contains($haystack, mb_strtolower($kw))) {
                return $kw;
            }
        }
        return null;
    }
}
