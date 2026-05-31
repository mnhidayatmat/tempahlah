<?php

namespace App\Services\Agent\Llm;

/**
 * Provider-agnostic tool schema. Adapters translate this into their own
 * wire shape (Anthropic input_schema, OpenAI parameters, Gemini
 * functionDeclarations).
 */
class ToolDefinition
{
    /**
     * @param  array  $schema  JSON-schema object: type=object, properties=[...], required=[...]
     */
    public function __construct(
        public string $name,
        public string $description,
        public array $schema,
    ) {}
}
