<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default LLM provider + model
    |--------------------------------------------------------------------------
    | Tenants can override either of these from /dashboard/integrations/agent
    | (provided the platform has configured a key for the chosen provider).
    */
    'default_provider' => env('AGENT_DEFAULT_PROVIDER', 'anthropic'),
    'default_model'    => env('AGENT_DEFAULT_MODEL', 'claude-haiku-4-5-20251001'),

    /*
    |--------------------------------------------------------------------------
    | Conversation + reply loop guardrails
    |--------------------------------------------------------------------------
    */
    'history_messages'  => 20,   // # of past WA messages fed to the LLM
    'max_tool_turns'    => 5,    // hard cap on tool-loop iterations per reply
    'max_output_tokens' => 800,
    'reply_timeout'     => 45,   // seconds — job timeout

    /*
    |--------------------------------------------------------------------------
    | Per-tenant default daily cap
    |--------------------------------------------------------------------------
    | Owner can lower this in settings. Platform-wide hard ceiling enforced
    | regardless of tenant config (cost protection).
    */
    'default_daily_cap' => 200,
    'platform_max_cap'  => 500,

    /*
    |--------------------------------------------------------------------------
    | Per-(tenant, phone) soft cap — silently drop after this many agent
    | replies to the same phone in 24h. Protects against spam abuse.
    |--------------------------------------------------------------------------
    */
    'per_phone_daily_cap' => 30,

    /*
    |--------------------------------------------------------------------------
    | Stale inbound cutoff — never reply to an inbound message that arrived
    | more than this many minutes before the job runs. Protects guests from
    | "out of the blue" robot replies if a queue backlog, sidecar restart,
    | or any other delay holds the job. The customer should NEVER receive
    | an unsolicited-feeling reply hours after their original message.
    |--------------------------------------------------------------------------
    */
    'max_inbound_age_minutes' => (int) env('AGENT_MAX_INBOUND_AGE_MINUTES', 5),

    /*
    |--------------------------------------------------------------------------
    | Photos
    |--------------------------------------------------------------------------
    */
    'max_photos_per_reply' => 4,

    /*
    |--------------------------------------------------------------------------
    | Providers
    |--------------------------------------------------------------------------
    | Each entry: api_key (env), base_url, default model, list of available
    | model labels for the settings dropdown. A provider is only offered to
    | tenants if api_key is present.
    */
    'providers' => [

        'anthropic' => [
            'label'    => 'Anthropic (Claude)',
            'api_key'  => env('ANTHROPIC_API_KEY'),
            'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com/v1'),
            'version'  => env('ANTHROPIC_VERSION', '2023-06-01'),
            'default'  => 'claude-haiku-4-5-20251001',
            'models'   => [
                'claude-haiku-4-5-20251001' => 'Claude Haiku 4.5 (fast, cheap)',
                'claude-sonnet-4-6'         => 'Claude Sonnet 4.6 (balanced)',
                'claude-opus-4-6'           => 'Claude Opus 4.6 (premium)',
            ],
        ],

        'gemini' => [
            'label'    => 'Google Gemini',
            'api_key'  => env('GEMINI_API_KEY'),
            'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
            'default'  => 'gemini-2.5-flash',
            'models'   => [
                'gemini-2.5-flash' => 'Gemini 2.5 Flash (fast)',
                'gemini-2.5-pro'   => 'Gemini 2.5 Pro (balanced)',
            ],
        ],

        'deepseek' => [
            'label'    => 'DeepSeek',
            'api_key'  => env('DEEPSEEK_API_KEY'),
            'base_url' => env('DEEPSEEK_BASE_URL', 'https://api.deepseek.com/v1'),
            // v4-flash is the new fast/cheap tier; v4-pro the balanced tier.
            // deepseek-chat + deepseek-reasoner are sunsetting 2026-07-24.
            'default'  => 'deepseek-v4-flash',
            'models'   => [
                'deepseek-v4-flash' => 'DeepSeek v4 Flash (fast, cheap)',
                'deepseek-v4-pro'   => 'DeepSeek v4 Pro (balanced)',
                'deepseek-chat'     => 'DeepSeek Chat (legacy — deprecated 2026-07-24)',
                'deepseek-reasoner' => 'DeepSeek Reasoner (legacy — deprecated 2026-07-24)',
            ],
        ],

        'glm' => [
            'label'    => 'ZhipuAI GLM',
            'api_key'  => env('GLM_API_KEY'),
            'base_url' => env('GLM_BASE_URL', 'https://open.bigmodel.cn/api/paas/v4'),
            'default'  => 'glm-4.5-air',
            'models'   => [
                'glm-4.5-air' => 'GLM-4.5 Air (fast)',
                'glm-4.5'     => 'GLM-4.5 (balanced)',
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Settings defaults
    |--------------------------------------------------------------------------
    | Used by AgentSettings when the tenant_integrations row is missing keys.
    */
    'defaults' => [
        'enabled'             => false,
        'persona'             => 'friendly',  // friendly | formal | concise
        'greeting_bm'         => 'Salam! Saya pembantu AI {{tenant_name}}. Boleh tolong saya tahu nak tempah bila, berapa orang dan homestay mana satu?',
        'greeting_en'         => 'Hi! I\'m {{tenant_name}}\'s AI assistant. To help, may I know your dates, number of guests, and which homestay you\'re interested in?',
        'signature'           => '',
        'business_hours'      => null,        // null = always-on
        'out_of_hours_bm'     => 'Terima kasih hubungi {{tenant_name}}. Kami akan balas semasa waktu operasi.',
        'out_of_hours_en'     => 'Thanks for reaching out to {{tenant_name}}. We\'ll reply during business hours.',
        'escalation_keywords' => ['manager', 'owner', 'complaint', 'refund', 'tuan rumah', 'aduan'],
        'handoff_phone'       => null,
        'daily_cap'           => 200,
        'custom_knowledge'    => '',
        'send_photos_enabled' => true,
        'reply_languages'     => 'auto',      // auto | ms | en
        'training_qa'         => [],          // [{q, a, source: auto|custom}] — seeded from tenant info
        'training_qa_seeded'  => false,       // true once the owner has saved/edited the FAQ
    ],

];
