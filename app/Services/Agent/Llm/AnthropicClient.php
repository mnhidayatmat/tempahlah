<?php

namespace App\Services\Agent\Llm;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Anthropic Messages API adapter.
 *
 *   POST {base_url}/messages
 *
 * Native tool-use format: each assistant turn can contain content blocks of
 * type 'text' and 'tool_use'; tool results come back as user-role messages
 * containing a 'tool_result' block with a tool_use_id referencing the call.
 */
class AnthropicClient implements LlmClient
{
    public function __construct(
        protected string $apiKey,
        protected string $baseUrl,
        protected string $version,
        protected string $model,
    ) {}

    public function provider(): string { return 'anthropic'; }
    public function model(): string    { return $this->model; }

    public function chat(string $system, array $messages, array $tools, array $options = []): LlmResponse
    {
        $body = array_filter([
            'model'       => $this->model,
            'max_tokens'  => $options['max_tokens'] ?? config('agent.max_output_tokens', 800),
            'temperature' => $options['temperature'] ?? 0.4,
            'system'      => $system,
            'messages'    => $this->encodeMessages($messages),
            'tools'       => $this->encodeTools($tools),
        ], fn ($v) => $v !== null && $v !== []);

        $response = Http::baseUrl(rtrim($this->baseUrl, '/'))
            ->withHeaders([
                'x-api-key'         => $this->apiKey,
                'anthropic-version' => $this->version,
                'content-type'      => 'application/json',
            ])
            ->timeout($options['timeout'] ?? 45)
            ->post('/messages', $body);

        if (! $response->successful()) {
            throw new RuntimeException("Anthropic API error {$response->status()}: ".$response->body());
        }

        $json = $response->json();

        $text = null;
        $toolCalls = [];
        foreach ($json['content'] ?? [] as $block) {
            if (($block['type'] ?? null) === 'text') {
                $text = trim(($text ?? '').' '.($block['text'] ?? ''));
            } elseif (($block['type'] ?? null) === 'tool_use') {
                $toolCalls[] = new ToolCall(
                    id:        (string) ($block['id'] ?? ''),
                    name:      (string) ($block['name'] ?? ''),
                    arguments: (array)  ($block['input'] ?? []),
                );
            }
        }

        return new LlmResponse(
            stopReason: (string) ($json['stop_reason'] ?? 'end_turn'),
            text:       $text ? trim($text) : null,
            toolCalls:  $toolCalls,
            tokensIn:   (int) ($json['usage']['input_tokens'] ?? 0),
            tokensOut:  (int) ($json['usage']['output_tokens'] ?? 0),
            raw:        $json,
        );
    }

    /** @param  LlmMessage[]  $messages */
    protected function encodeMessages(array $messages): array
    {
        $out = [];
        foreach ($messages as $m) {
            if ($m->role === 'user') {
                $out[] = ['role' => 'user', 'content' => $m->content ?? ''];
            } elseif ($m->role === 'assistant') {
                $blocks = [];
                if (! empty($m->content)) {
                    $blocks[] = ['type' => 'text', 'text' => $m->content];
                }
                foreach ($m->toolCalls as $tc) {
                    $blocks[] = [
                        'type'  => 'tool_use',
                        'id'    => $tc->id,
                        'name'  => $tc->name,
                        'input' => (object) $tc->arguments,
                    ];
                }
                $out[] = ['role' => 'assistant', 'content' => $blocks];
            } elseif ($m->role === 'tool') {
                // Anthropic wraps tool_result in a user-role message.
                $out[] = [
                    'role' => 'user',
                    'content' => [[
                        'type'        => 'tool_result',
                        'tool_use_id' => $m->toolUseId,
                        'content'     => $m->content ?? '',
                    ]],
                ];
            }
        }
        return $out;
    }

    /** @param  ToolDefinition[]  $tools */
    protected function encodeTools(array $tools): array
    {
        return array_map(fn (ToolDefinition $t) => [
            'name'         => $t->name,
            'description'  => $t->description,
            'input_schema' => $t->schema,
        ], $tools);
    }
}
