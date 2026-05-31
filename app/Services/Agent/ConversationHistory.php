<?php

namespace App\Services\Agent;

use App\Models\AgentConversation;
use App\Models\WhatsappMessage;
use App\Services\Agent\Llm\LlmMessage;

class ConversationHistory
{
    /**
     * Build a chat history array for the LLM from the conversation log.
     * Most-recent N messages, oldest first.
     *
     * @return LlmMessage[]
     */
    public function build(AgentConversation $convo, int $limit = 20): array
    {
        $rows = WhatsappMessage::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $convo->tenant_id)
            ->where('recipient_phone', $convo->guest_phone)
            ->whereIn('kind', [
                WhatsappMessage::KIND_INBOUND,
                WhatsappMessage::KIND_AGENT_REPLY,
            ])
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();

        $msgs = [];
        foreach ($rows as $row) {
            $text = trim((string) $row->body);
            if ($text === '') continue;

            $msgs[] = $row->direction === WhatsappMessage::DIRECTION_IN
                ? LlmMessage::user($text)
                : LlmMessage::assistant($text);
        }
        return $msgs;
    }

    /**
     * Guess the locale of the latest inbound message — 'ms' or 'en'.
     */
    public function detectLocale(AgentConversation $convo): string
    {
        $row = WhatsappMessage::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $convo->tenant_id)
            ->where('recipient_phone', $convo->guest_phone)
            ->where('direction', WhatsappMessage::DIRECTION_IN)
            ->latest('id')
            ->first();

        if (! $row) return 'en';

        $text = mb_strtolower((string) $row->body);
        $bmHints = ['nak', 'boleh', 'tempah', 'malam', 'bilik', 'berapa', 'tarikh', 'bila', 'ada', 'salam', 'apa', 'macam', 'tak', 'rumah', 'tuan', 'baik', 'ya', 'tidak'];
        $hits = 0;
        foreach ($bmHints as $w) {
            if (preg_match('/\b'.preg_quote($w, '/').'\b/u', $text)) $hits++;
        }
        return $hits >= 2 ? 'ms' : 'en';
    }
}
