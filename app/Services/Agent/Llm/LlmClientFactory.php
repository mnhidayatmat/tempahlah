<?php

namespace App\Services\Agent\Llm;

use RuntimeException;

class LlmClientFactory
{
    /**
     * Build a configured LlmClient for the requested provider.
     *
     * @throws RuntimeException if the provider is unknown or its API key
     *         is missing from config.
     */
    public function for(string $provider, ?string $model = null): LlmClient
    {
        $cfg = config("agent.providers.{$provider}");
        if (! $cfg) {
            throw new RuntimeException("Unknown agent provider: {$provider}");
        }
        if (empty($cfg['api_key'])) {
            throw new RuntimeException("Agent provider {$provider} has no API key configured.");
        }

        $chosen = $model ?: ($cfg['default'] ?? null);
        if (! $chosen) {
            throw new RuntimeException("No model configured for provider {$provider}.");
        }

        return match ($provider) {
            'anthropic' => new AnthropicClient(
                apiKey:  $cfg['api_key'],
                baseUrl: $cfg['base_url'],
                version: $cfg['version'] ?? '2023-06-01',
                model:   $chosen,
            ),
            'gemini' => new GeminiClient(
                apiKey:  $cfg['api_key'],
                baseUrl: $cfg['base_url'],
                model:   $chosen,
            ),
            'deepseek' => new DeepSeekClient(
                apiKey:  $cfg['api_key'],
                baseUrl: $cfg['base_url'],
                model:   $chosen,
            ),
            'glm' => new GlmClient(
                apiKey:  $cfg['api_key'],
                baseUrl: $cfg['base_url'],
                model:   $chosen,
            ),
            default => throw new RuntimeException("No adapter implemented for provider {$provider}"),
        };
    }

    /**
     * Providers the platform has keys configured for (the ones we'll
     * actually show in the settings dropdown).
     *
     * @return array<string, array{label:string, models:array}>
     */
    public function availableProviders(): array
    {
        $out = [];
        foreach ((array) config('agent.providers') as $key => $cfg) {
            if (! empty($cfg['api_key'])) {
                $out[$key] = [
                    'label'  => $cfg['label'] ?? $key,
                    'models' => $cfg['models'] ?? [],
                ];
            }
        }
        return $out;
    }
}
