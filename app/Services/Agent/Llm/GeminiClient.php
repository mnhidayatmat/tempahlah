<?php

namespace App\Services\Agent\Llm;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Google Gemini adapter.
 *
 *   POST {base_url}/models/{model}:generateContent?key={apiKey}
 *
 * Tool-use: tools[0].functionDeclarations = [...] ; model returns
 * candidates[0].content.parts[].functionCall ; tool results are sent
 * back as a user-role message with parts[].functionResponse.
 */
class GeminiClient implements LlmClient
{
    public function __construct(
        protected string $apiKey,
        protected string $baseUrl,
        protected string $model,
    ) {}

    public function provider(): string { return 'gemini'; }
    public function model(): string    { return $this->model; }

    public function chat(string $system, array $messages, array $tools, array $options = []): LlmResponse
    {
        $body = array_filter([
            'systemInstruction' => ['parts' => [['text' => $system]]],
            'contents'          => $this->encodeMessages($messages),
            'tools'             => $this->encodeTools($tools),
            'generationConfig'  => [
                'temperature'     => $options['temperature'] ?? 0.4,
                'maxOutputTokens' => $options['max_tokens']  ?? config('agent.max_output_tokens', 800),
            ],
        ], fn ($v) => $v !== null && $v !== []);

        $response = Http::baseUrl(rtrim($this->baseUrl, '/'))
            ->acceptJson()
            ->timeout($options['timeout'] ?? 45)
            ->post("/models/{$this->model}:generateContent?key={$this->apiKey}", $body);

        if (! $response->successful()) {
            throw new RuntimeException("Gemini API error {$response->status()}: ".$response->body());
        }

        $json = $response->json();
        $parts = $json['candidates'][0]['content']['parts'] ?? [];

        $text = null;
        $toolCalls = [];
        foreach ($parts as $part) {
            if (isset($part['text'])) {
                $text = trim(($text ?? '').' '.$part['text']);
            } elseif (isset($part['functionCall'])) {
                $fn = $part['functionCall'];
                $toolCalls[] = new ToolCall(
                    // Gemini doesn't return ids — synthesise one and echo back.
                    id:        'call_'.Str::random(8),
                    name:      (string) ($fn['name'] ?? ''),
                    arguments: (array)  ($fn['args'] ?? []),
                );
            }
        }

        $finish = (string) ($json['candidates'][0]['finishReason'] ?? 'STOP');
        $stopReason = match ($finish) {
            'STOP'      => empty($toolCalls) ? 'end_turn' : 'tool_use',
            'MAX_TOKENS'=> 'max_tokens',
            default     => 'end_turn',
        };

        return new LlmResponse(
            stopReason: $stopReason,
            text:       $text ? trim($text) : null,
            toolCalls:  $toolCalls,
            tokensIn:   (int) ($json['usageMetadata']['promptTokenCount'] ?? 0),
            tokensOut:  (int) ($json['usageMetadata']['candidatesTokenCount'] ?? 0),
            raw:        $json,
        );
    }

    /** @param  LlmMessage[]  $messages */
    protected function encodeMessages(array $messages): array
    {
        $out = [];
        foreach ($messages as $m) {
            if ($m->role === 'user') {
                $out[] = ['role' => 'user', 'parts' => [['text' => $m->content ?? '']]];
            } elseif ($m->role === 'assistant') {
                $parts = [];
                if (! empty($m->content)) {
                    $parts[] = ['text' => $m->content];
                }
                foreach ($m->toolCalls as $tc) {
                    $parts[] = ['functionCall' => [
                        'name' => $tc->name,
                        'args' => (object) $tc->arguments,
                    ]];
                }
                $out[] = ['role' => 'model', 'parts' => $parts];
            } elseif ($m->role === 'tool') {
                $decoded = json_decode($m->content ?? '{}', true);
                $payload = is_array($decoded) ? $decoded : ['result' => $m->content];
                $out[] = ['role' => 'user', 'parts' => [[
                    'functionResponse' => [
                        'name' => $m->toolName,
                        'response' => $payload,
                    ],
                ]]];
            }
        }
        return $out;
    }

    /** @param  ToolDefinition[]  $tools */
    protected function encodeTools(array $tools): array
    {
        if (empty($tools)) return [];
        return [[
            'functionDeclarations' => array_map(fn (ToolDefinition $t) => [
                'name'        => $t->name,
                'description' => $t->description,
                'parameters'  => $t->schema,
            ], $tools),
        ]];
    }
}
