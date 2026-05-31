<?php

namespace App\Services\Agent\Llm;

interface LlmClient
{
    /**
     * Run one round of chat completion with tool-use enabled.
     *
     * @param  string         $system    System prompt
     * @param  LlmMessage[]   $messages  Chat history in turn order
     * @param  ToolDefinition[] $tools   Available tools
     * @param  array          $options   max_tokens, temperature, etc.
     */
    public function chat(string $system, array $messages, array $tools, array $options = []): LlmResponse;

    public function provider(): string;

    public function model(): string;
}
