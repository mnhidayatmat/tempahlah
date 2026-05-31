<?php

namespace App\Services\Agent\Llm;

/**
 * Normalised response from any provider after one chat round.
 *
 *   stopReason:
 *     'end_turn'   — model returned a normal text reply, done
 *     'tool_use'   — model wants tool_calls executed; we feed results back
 *     'max_tokens' — model was cut off
 *
 *   tokensIn / tokensOut — for usage accounting; 0 if provider didn't report.
 */
class LlmResponse
{
    /** @param  ToolCall[]  $toolCalls */
    public function __construct(
        public string $stopReason,
        public ?string $text = null,
        public array $toolCalls = [],
        public int $tokensIn = 0,
        public int $tokensOut = 0,
        public array $raw = [],
    ) {}

    public function wantsTools(): bool
    {
        return ! empty($this->toolCalls);
    }
}
