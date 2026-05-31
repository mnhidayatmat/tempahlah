<?php

namespace App\Services\Agent\Llm;

/**
 * A function/tool the LLM wants us to execute.
 *
 *   id:        provider-issued identifier (echo back in tool result)
 *   name:      tool name, matches Tool::name()
 *   arguments: decoded JSON object — already an associative array
 */
class ToolCall
{
    public function __construct(
        public string $id,
        public string $name,
        public array $arguments,
    ) {}
}
