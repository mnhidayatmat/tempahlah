<?php

namespace App\Services\Agent\Tools;

use App\Models\AgentConversation;
use App\Models\Tenant;

/**
 * Per-reply context object handed to every tool. Pre-resolves the
 * tenant + conversation + locale so tools don't have to re-query.
 */
class ToolContext
{
    public function __construct(
        public Tenant $tenant,
        public AgentConversation $conversation,
        public string $locale = 'en', // 'ms' | 'en'
    ) {}
}
