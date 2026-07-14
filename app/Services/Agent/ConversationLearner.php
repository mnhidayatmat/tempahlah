<?php

namespace App\Services\Agent;

use App\Models\AgentConversation;
use App\Models\AgentLearnedFaq;
use App\Models\Tenant;
use App\Models\WhatsappMessage;
use App\Services\Agent\Llm\LlmClientFactory;
use App\Services\Agent\Llm\LlmMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * The "learn from day-to-day conversations" distiller.
 *
 * Reads a tenant's recent WhatsApp transcripts, asks the tenant's own LLM to
 * distil (a) recurring questions that already have a good answer and (b) gaps
 * the agent couldn't answer, and stores each as a PENDING AgentLearnedFaq for
 * the host to review. It NEVER edits the live FAQ — approval is a separate,
 * host-driven step in the settings UI.
 *
 * Grounding + privacy are enforced two ways: the distiller prompt forbids
 * inventing prices/policies and demands generic (PII-free) phrasing, and a
 * post-parse scrub strips emails / phone numbers / long digit runs from every
 * stored string.
 */
class ConversationLearner
{
    public function __construct(protected LlmClientFactory $factory) {}

    /**
     * Distil suggestions for one tenant. Returns the number of NEW pending
     * rows created (0 if nothing new, the LLM is unavailable, or there's
     * nothing to learn from). Safe to call repeatedly — deduped against the
     * live FAQ and existing suggestions.
     */
    public function learn(Tenant $tenant): int
    {
        $cfg = config('agent.learning');
        if (! ($cfg['enabled'] ?? true)) {
            return 0;
        }

        $settings = AgentSettings::forTenant($tenant);

        $convos = $this->recentConversations($tenant, (int) $cfg['lookback_days'], (int) $cfg['max_conversations_per_run']);
        if ($convos->isEmpty()) {
            return 0;
        }

        $transcript = $this->buildTranscriptBundle($tenant, $convos, $cfg);
        if (trim($transcript) === '') {
            return 0;
        }

        // Everything the agent already knows — so the distiller doesn't
        // re-propose covered ground.
        $known = $this->knownQuestions($tenant, $settings);

        try {
            $llm = $this->factory->for($settings->llmProvider, $settings->llmModel);
        } catch (\Throwable $e) {
            Log::info('ConversationLearner: LLM unavailable, skipping', [
                'tenant_id' => $tenant->id, 'err' => $e->getMessage(),
            ]);
            return 0;
        }

        $system = $this->distillPrompt($tenant, $known, (int) $cfg['max_suggestions_per_run']);

        try {
            $response = $llm->chat($system, [LlmMessage::user($transcript)], [], [
                'max_tokens'  => (int) $cfg['max_output_tokens'],
                'temperature' => 0.2,
            ]);
        } catch (\Throwable $e) {
            Log::warning('ConversationLearner: LLM call failed', [
                'tenant_id' => $tenant->id, 'err' => $e->getMessage(),
            ]);
            return 0;
        }

        $items = $this->parseItems((string) $response->text);
        if (empty($items)) {
            return 0;
        }

        return $this->store($tenant, $items, $known, (int) $cfg['max_suggestions_per_run']);
    }

    /** @return \Illuminate\Support\Collection<int, AgentConversation> */
    protected function recentConversations(Tenant $tenant, int $lookbackDays, int $limit)
    {
        $since = Carbon::now()->subDays(max(1, $lookbackDays));

        return AgentConversation::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereNotNull('last_inbound_at')
            ->where('last_inbound_at', '>=', $since)
            ->where('guest_phone', '!=', '+playground') // exclude the owner's test thread
            ->orderByDesc('last_inbound_at')
            ->limit(max(1, $limit))
            ->get();
    }

    /**
     * Concatenate each conversation's recent messages into one bundle, capped
     * at max_transcript_chars so we never blow the context / cost budget.
     */
    protected function buildTranscriptBundle(Tenant $tenant, $convos, array $cfg): string
    {
        $perConvo = (int) $cfg['max_messages_per_convo'];
        $charCap  = (int) $cfg['max_transcript_chars'];

        $blocks = [];
        $used = 0;
        $n = 0;

        foreach ($convos as $convo) {
            $rows = WhatsappMessage::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('recipient_phone', $convo->guest_phone)
                ->whereIn('kind', [WhatsappMessage::KIND_INBOUND, WhatsappMessage::KIND_AGENT_REPLY])
                ->orderByDesc('id')
                ->limit($perConvo)
                ->get()
                ->reverse()
                ->values();

            $lines = [];
            foreach ($rows as $row) {
                $text = trim((string) $row->body);
                if ($text === '') continue;
                $who = $row->direction === WhatsappMessage::DIRECTION_IN ? 'Guest' : 'Assistant';
                $lines[] = "{$who}: {$text}";
            }
            if (empty($lines)) continue;

            $n++;
            $block = "--- Conversation {$n} ---\n".implode("\n", $lines);
            if ($used + mb_strlen($block) > $charCap) {
                break;
            }
            $blocks[] = $block;
            $used += mb_strlen($block);
        }

        return implode("\n\n", $blocks);
    }

    /**
     * Normalised set of questions the agent already covers (live FAQ + existing
     * pending/approved suggestions) so we don't re-propose them.
     *
     * @return array<string, true>  keyed by normalised question
     */
    protected function knownQuestions(Tenant $tenant, AgentSettings $settings): array
    {
        $known = [];

        foreach ($settings->trainingQa as $pair) {
            $known[$this->normalise($pair['q'] ?? '')] = true;
        }

        $existing = AgentLearnedFaq::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereIn('status', [AgentLearnedFaq::STATUS_PENDING, AgentLearnedFaq::STATUS_APPROVED])
            ->pluck('question');
        foreach ($existing as $q) {
            $known[$this->normalise($q)] = true;
        }

        unset($known['']);
        return $known;
    }

    protected function distillPrompt(Tenant $tenant, array $known, int $max): string
    {
        $biz = $tenant->business_name ?? 'this homestay';
        $knownList = empty($known)
            ? '(none yet)'
            : collect(array_keys($known))->take(60)->map(fn ($k) => "- {$k}")->implode("\n");

        return <<<PROMPT
You analyse WhatsApp chat transcripts between guests and the AI booking assistant for {$biz}, a Malaysian homestay. Your job is to help the owner improve the assistant's knowledge base by spotting what guests actually ask.

Produce a JSON array (and NOTHING else) of at most {$max} items. Each item:
{
  "question": "a clear, generic version of the question guests asked (English, short)",
  "answer": "the correct answer IF it is clearly present in the transcripts (what the assistant or owner actually said) or trivially grounded — otherwise null",
  "kind": "recurring" or "gap",
  "example": "one short real phrasing a guest used, with names/phones/addresses removed"
}

Rules:
- "recurring" = a question asked by MORE THAN ONE guest (or clearly common) that HAS a good answer visible in the chats. Fill "answer".
- "gap" = a question the assistant could NOT answer, escalated, or answered poorly, OR where the guest seemed unsatisfied. Set "answer" to null.
- NEVER invent prices, dates, addresses, policies, or facts. If you're not sure of the answer from the transcripts, it's a "gap" with null answer.
- Do NOT include any guest's name, phone number, email, or booking-specific details in "question", "answer", or "example". Keep everything generic.
- Do NOT propose anything already covered by the assistant's existing knowledge below.
- Prefer questions that recur or reveal a real gap. Skip one-off small talk.
- If there's nothing worth adding, return [].

The assistant ALREADY knows how to answer these (do not repeat them):
{$knownList}

Return ONLY the JSON array.
PROMPT;
    }

    /**
     * Pull the JSON array out of the model's reply (tolerating code fences /
     * stray prose) and coerce to a clean list.
     *
     * @return array<int, array{question:string, answer:?string, kind:string, example:?string}>
     */
    protected function parseItems(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') return [];

        $start = strpos($raw, '[');
        $end   = strrpos($raw, ']');
        if ($start === false || $end === false || $end <= $start) {
            return [];
        }
        $json = substr($raw, $start, $end - $start + 1);

        $decoded = json_decode($json, true);
        if (! is_array($decoded)) return [];

        $out = [];
        foreach ($decoded as $item) {
            if (! is_array($item)) continue;
            $q = trim((string) ($item['question'] ?? ''));
            if ($q === '') continue;

            $answer = $item['answer'] ?? null;
            $answer = is_string($answer) ? trim($answer) : null;
            if ($answer === '' || strtolower((string) $answer) === 'null') {
                $answer = null;
            }

            $kind = ($item['kind'] ?? '') === 'gap' || $answer === null
                ? AgentLearnedFaq::KIND_GAP
                : AgentLearnedFaq::KIND_RECURRING;

            $example = isset($item['example']) ? trim((string) $item['example']) : '';

            $out[] = [
                'question' => Str::limit($this->scrub($q), 390, ''),
                'answer'   => $answer !== null ? $this->scrub($answer) : null,
                'kind'     => $kind,
                'example'  => $example !== '' ? Str::limit($this->scrub($example), 200, '') : null,
            ];
        }
        return $out;
    }

    /**
     * Persist new, non-duplicate suggestions. Returns the count created.
     *
     * @param array<int, array{question:string, answer:?string, kind:string, example:?string}> $items
     * @param array<string, true> $known
     */
    protected function store(Tenant $tenant, array $items, array $known, int $max): int
    {
        $created = 0;
        $seen = $known; // running set so a batch can't self-duplicate

        foreach ($items as $item) {
            if ($created >= $max) break;

            $norm = $this->normalise($item['question']);
            if ($norm === '' || isset($seen[$norm])) {
                continue;
            }
            $seen[$norm] = true;

            $row = new AgentLearnedFaq([
                'tenant_id'       => $tenant->id, // explicit — no web tenant context in the job
                'question'        => $item['question'],
                'suggested_answer' => $item['answer'],
                'kind'            => $item['kind'],
                'status'          => AgentLearnedFaq::STATUS_PENDING,
                'occurrences'     => 1,
                'example_phrases' => $item['example'] !== null ? [$item['example']] : null,
                'meta'            => ['generated_at' => Carbon::now()->toIso8601String()],
            ]);
            $row->save();
            $created++;
        }

        if ($created > 0) {
            Log::info('ConversationLearner: stored suggestions', [
                'tenant_id' => $tenant->id, 'count' => $created,
            ]);
        }

        return $created;
    }

    /** Strip emails, phone numbers, and long digit runs (defence-in-depth PII scrub). */
    protected function scrub(string $text): string
    {
        $text = preg_replace('/[\w.+-]+@[\w-]+\.[\w.-]+/u', '[email]', $text);
        // Phone-like: optional +, then 7+ digits possibly split by spaces/dashes.
        $text = preg_replace('/\+?\d[\d\s\-]{6,}\d/u', '[number]', $text);
        return trim((string) $text);
    }

    /** Normalise a question for dedup: lowercase, strip non-alphanumerics. */
    protected function normalise(string $q): string
    {
        $q = mb_strtolower(trim($q));
        $q = preg_replace('/[^a-z0-9]+/u', '', $q) ?? '';
        return $q;
    }
}
