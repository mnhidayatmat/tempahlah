<?php

namespace App\Services\Agent;

use App\Models\AgentConversation;
use App\Models\AgentUsageDaily;
use App\Models\Tenant;
use App\Models\WhatsappMessage;
use App\Services\Agent\Llm\LlmClient;
use App\Services\Agent\Llm\LlmClientFactory;
use App\Services\Agent\Llm\LlmMessage;
use App\Services\Agent\Tools\ToolContext;
use App\Services\WhatsApp\WhatsappMessenger;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Orchestrates one inbound-message → final-reply round.
 *
 *   1. Build system prompt + chat history.
 *   2. Call the configured LLM with the tool catalogue.
 *   3. If it returns tool_use, execute each tool and feed results back.
 *   4. Loop until end_turn or max_tool_turns hit.
 *   5. Send the final text via WhatsappMessenger::dispatchAgentReply().
 *
 * Returns the WhatsappMessage row that was queued (or null on no-op).
 */
class AgentService
{
    public function __construct(
        protected LlmClientFactory $factory,
        protected ToolRegistry $tools,
        protected SystemPromptBuilder $prompts,
        protected ConversationHistory $history,
    ) {}

    /**
     * Live handler — sends a real WhatsApp message to the guest.
     */
    public function handle(Tenant $tenant, AgentConversation $convo, WhatsappMessage $inbound): ?WhatsappMessage
    {
        return $this->run($tenant, $convo, dryRun: false);
    }

    /**
     * Playground: same loop, but text is returned instead of sent.
     * Returns a string for the UI to display.
     */
    public function dryRun(Tenant $tenant, AgentConversation $convo, string $userText): string
    {
        // Synthesize a transient user turn (don't persist).
        $result = $this->run($tenant, $convo, dryRun: true, transientUserText: $userText);
        return $result['text'] ?? '';
    }

    /**
     * @return mixed  WhatsappMessage|null for live, or ['text'=>string] for dry-run.
     */
    protected function run(
        Tenant $tenant,
        AgentConversation $convo,
        bool $dryRun,
        ?string $transientUserText = null,
    ): mixed {
        $settings = AgentSettings::forTenant($tenant);

        try {
            $llm = $this->factory->for($settings->llmProvider, $settings->llmModel);
        } catch (RuntimeException $e) {
            Log::warning('Agent: LLM client unavailable', [
                'tenant_id' => $tenant->id,
                'err' => $e->getMessage(),
            ]);
            return $dryRun ? ['text' => ''] : null;
        }

        $locale = $this->history->detectLocale($convo);

        $system = $this->prompts->build($tenant, $settings, $locale);
        $history = $this->history->build($convo, (int) config('agent.history_messages', 20));
        if ($transientUserText !== null) {
            $history[] = LlmMessage::user($transientUserText);
        }

        $exclude = $settings->sendPhotosEnabled ? [] : ['send_photos'];
        $toolDefs = $this->tools->definitions($exclude);

        $ctx = new ToolContext($tenant, $convo, $locale);

        $maxTurns = (int) config('agent.max_tool_turns', 5);
        $totalIn = 0;
        $totalOut = 0;
        $toolCallCount = 0;
        $finalText = null;

        // Disable send_photos in dry-run mode — playground should not
        // actually push images to a customer.
        $effectiveToolDefs = $dryRun
            ? array_values(array_filter($toolDefs, fn ($d) => $d->name !== 'send_photos' && $d->name !== 'escalate_to_human'))
            : $toolDefs;

        for ($turn = 0; $turn < $maxTurns; $turn++) {
            try {
                $response = $llm->chat($system, $history, $effectiveToolDefs);
            } catch (\Throwable $e) {
                Log::warning('Agent: LLM call failed', [
                    'tenant_id' => $tenant->id,
                    'provider' => $llm->provider(),
                    'err' => $e->getMessage(),
                ]);
                $finalText = $this->fallbackMessage($locale);
                break;
            }

            $totalIn  += $response->tokensIn;
            $totalOut += $response->tokensOut;

            if (! $response->wantsTools()) {
                $finalText = $response->text;
                break;
            }

            // Persist the assistant's tool_use turn to history so the
            // tool_result we add next references valid ids.
            $history[] = LlmMessage::assistantToolCalls($response->toolCalls, $response->text);

            foreach ($response->toolCalls as $tc) {
                $toolCallCount++;
                $tool = $this->tools->get($tc->name);
                if (! $tool) {
                    $history[] = LlmMessage::toolResult($tc->id, $tc->name, json_encode([
                        'error' => "Unknown tool: {$tc->name}",
                    ]));
                    continue;
                }
                try {
                    $result = $tool->execute($tc->arguments, $ctx);
                } catch (\Throwable $e) {
                    $result = ['error' => $e->getMessage()];
                }

                // If the agent escalated, mark a hint so we stop looping
                // and the LLM crafts a brief handoff message.
                if ($tc->name === 'escalate_to_human') {
                    $convo->refresh();
                }

                $history[] = LlmMessage::toolResult(
                    $tc->id,
                    $tc->name,
                    json_encode($result, JSON_UNESCAPED_UNICODE),
                );
            }
        }

        $finalText = trim((string) $finalText);
        if ($finalText === '') {
            $finalText = $this->fallbackMessage($locale);
        }

        // Accounting + conversation bookkeeping (always, even dry-run, so
        // the meter reflects actual API costs).
        $this->recordUsage($tenant, $llm, $totalIn, $totalOut, $toolCallCount, false);

        if ($dryRun) {
            return ['text' => $finalText];
        }

        // Send the WhatsApp reply.
        $msg = WhatsappMessenger::dispatchAgentReply(
            tenant:         $tenant,
            recipientPhone: $convo->guest_phone,
            body:           $finalText,
        );

        $convo->update([
            'last_outbound_at' => now(),
            'message_count'    => ($convo->message_count ?? 0) + 1,
            'locale'           => $locale,
        ]);

        return $msg;
    }

    protected function recordUsage(
        Tenant $tenant,
        LlmClient $llm,
        int $tokensIn,
        int $tokensOut,
        int $toolCalls,
        bool $inboundOnly,
    ): void {
        $row = AgentUsageDaily::todayFor($tenant->id);
        $row->provider = $llm->provider();
        $row->model = $llm->model();
        if (! $inboundOnly) {
            $row->reply_count = ($row->reply_count ?? 0) + 1;
            $row->tool_calls  = ($row->tool_calls ?? 0) + $toolCalls;
            $row->tokens_in   = ($row->tokens_in ?? 0) + $tokensIn;
            $row->tokens_out  = ($row->tokens_out ?? 0) + $tokensOut;
        }
        $row->save();
    }

    protected function fallbackMessage(string $locale): string
    {
        return $locale === 'ms'
            ? 'Maaf, saya tak dapat balas sekarang. Tuan rumah akan menyambung perbualan ini sebentar lagi.'
            : "Sorry, I can't respond right now. The owner will jump in shortly.";
    }
}
