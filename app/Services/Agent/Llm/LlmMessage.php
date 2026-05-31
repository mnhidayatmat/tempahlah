<?php

namespace App\Services\Agent\Llm;

/**
 * Normalised chat-turn DTO.
 *
 *   role:        'user' | 'assistant' | 'tool'
 *   content:     plain text (or null if tool_calls / tool_result is set)
 *   tool_calls:  for role=assistant — list of ToolCall the model wants run
 *   tool_use_id: for role=tool — the id of the tool call this is the result of
 *   tool_name:   for role=tool — name of the tool
 *
 * The provider adapters convert this into their own wire format.
 */
class LlmMessage
{
    /** @param  ToolCall[]  $toolCalls */
    public function __construct(
        public string $role,
        public ?string $content = null,
        public array $toolCalls = [],
        public ?string $toolUseId = null,
        public ?string $toolName = null,
    ) {}

    public static function user(string $text): self
    {
        return new self('user', $text);
    }

    public static function assistant(string $text): self
    {
        return new self('assistant', $text);
    }

    /** @param  ToolCall[]  $calls */
    public static function assistantToolCalls(array $calls, ?string $text = null): self
    {
        return new self('assistant', $text, $calls);
    }

    public static function toolResult(string $toolUseId, string $toolName, string $content): self
    {
        return new self('tool', $content, [], $toolUseId, $toolName);
    }
}
