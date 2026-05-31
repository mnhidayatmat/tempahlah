<?php

namespace App\Services\Agent\Llm;

class DeepSeekClient extends OpenAiCompatibleClient
{
    public function provider(): string { return 'deepseek'; }
}
