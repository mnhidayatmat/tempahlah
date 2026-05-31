<?php

namespace App\Services\Agent\Llm;

class GlmClient extends OpenAiCompatibleClient
{
    public function provider(): string { return 'glm'; }
}
