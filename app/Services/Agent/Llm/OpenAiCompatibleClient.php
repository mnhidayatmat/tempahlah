<?php

namespace App\Services\Agent\Llm;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Base for OpenAI-style /chat/completions providers (DeepSeek, GLM, etc).
 *
 * Wire format follows OpenAI's tool-use spec:
 *   - tools[].function = { name, description, parameters }
 *   - assistant turn:   tool_calls: [{ id, type:'function', function:{ name, arguments(JSON string) } }]
 *   - tool turn:        role:'tool', tool_call_id, content
 */
class OpenAiCompatibleClient implements LlmClient
{
    public function __construct(
        protected string $apiKey,
        protected string $baseUrl,
        protected string $model,
    ) {}

    public function provider(): string { return 'openai_compatible'; }
    public function model(): string    { return $this->model; }

    public function chat(string $system, array $messages, array $tools, array $options = []): LlmResponse
    {
        $body = array_filter([
            'model'       => $this->model,
            'messages'    => $this->encodeMessages($system, $messages),
            'tools'       => $this->encodeTools($tools),
            'temperature' => $options['temperature'] ?? 0.4,
            'max_tokens'  => $options['max_tokens'] ?? config('agent.max_output_tokens', 800),
        ], fn ($v) => $v !== null && $v !== []);

        $response = Http::baseUrl(rtrim($this->baseUrl, '/'))
            ->withToken($this->apiKey)
            ->acceptJson()
            ->timeout($options['timeout'] ?? 45)
            ->post('/chat/completions', $body);

        if (! $response->successful()) {
            throw new RuntimeException(static::class." error {$response->status()}: ".$response->body());
        }

        $json = $response->json();
        $choice = $json['choices'][0] ?? [];
        $msg = $choice['message'] ?? [];

        $toolCalls = [];
        foreach (($msg['tool_calls'] ?? []) as $tc) {
            $args = $tc['function']['arguments'] ?? '{}';
            $decoded = is_string($args) ? json_decode($args, true) : (array) $args;
            $toolCalls[] = new ToolCall(
                id:        (string) ($tc['id'] ?? ''),
                name:      (string) ($tc['function']['name'] ?? ''),
                arguments: (array) ($decoded ?? []),
            );
        }

        $finish = (string) ($choice['finish_reason'] ?? 'stop');
        $stopReason = match ($finish) {
            'tool_calls' => 'tool_use',
            'length'     => 'max_tokens',
            default      => 'end_turn',
        };

        return new LlmResponse(
            stopReason: $stopReason,
            text:       $msg['content'] ?? null,
            toolCalls:  $toolCalls,
            tokensIn:   (int) ($json['usage']['prompt_tokens'] ?? 0),
            tokensOut:  (int) ($json['usage']['completion_tokens'] ?? 0),
            raw:        $json,
        );
    }

    /** @param  LlmMessage[]  $messages */
    protected function encodeMessages(string $system, array $messages): array
    {
        $out = [['role' => 'system', 'content' => $system]];
        foreach ($messages as $m) {
            if ($m->role === 'user') {
                $out[] = ['role' => 'user', 'content' => $m->content ?? ''];
            } elseif ($m->role === 'assistant') {
                $entry = ['role' => 'assistant', 'content' => $m->content ?? ''];
                if (! empty($m->toolCalls)) {
                    $entry['tool_calls'] = array_map(fn (ToolCall $tc) => [
                        'id'       => $tc->id,
                        'type'     => 'function',
                        'function' => [
                            'name'      => $tc->name,
                            'arguments' => json_encode($tc->arguments, JSON_UNESCAPED_UNICODE),
                        ],
                    ], $m->toolCalls);
                }
                $out[] = $entry;
            } elseif ($m->role === 'tool') {
                $out[] = [
                    'role'         => 'tool',
                    'tool_call_id' => $m->toolUseId,
                    'content'      => $m->content ?? '',
                ];
            }
        }
        return $out;
    }

    /** @param  ToolDefinition[]  $tools */
    protected function encodeTools(array $tools): array
    {
        return array_map(fn (ToolDefinition $t) => [
            'type' => 'function',
            'function' => [
                'name'        => $t->name,
                'description' => $t->description,
                'parameters'  => $t->schema,
            ],
        ], $tools);
    }
}
