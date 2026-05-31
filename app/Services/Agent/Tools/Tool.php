<?php

namespace App\Services\Agent\Tools;

use App\Models\AgentConversation;
use App\Models\Tenant;
use App\Services\Agent\Llm\ToolDefinition;

/**
 * Contract for an agent tool.
 *
 * Tools are pure functions from JSON args → JSON result. Side effects
 * (sending photos via WhatsApp, escalating a conversation) are allowed
 * but must be expressed as a final summary in the returned array so the
 * LLM can narrate the action back to the guest.
 */
abstract class Tool
{
    abstract public function name(): string;

    abstract public function definition(): ToolDefinition;

    /**
     * @return array  Anything json-serialisable. Will be encoded to JSON
     *                and fed back into the model as the tool_result.
     */
    abstract public function execute(array $args, ToolContext $ctx): array;
}
