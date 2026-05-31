<?php

namespace App\Services\Agent\Tools;

use App\Models\PropertyPhoto;
use App\Services\Agent\Llm\ToolDefinition;
use App\Services\WhatsApp\WhatsappMessenger;

class SendPhotosTool extends Tool
{
    public function name(): string { return 'send_photos'; }

    public function definition(): ToolDefinition
    {
        $cats = array_keys(PropertyPhoto::categories());
        return new ToolDefinition(
            name: $this->name(),
            description: 'Send actual photo images to the guest via WhatsApp. Use this whenever the guest asks "can I see X" — do NOT paste image URLs as text. Each call sends up to 4 photos. If category is omitted, the most appealing photos are picked.',
            schema: [
                'type' => 'object',
                'properties' => [
                    'property_id' => ['type' => 'integer'],
                    'category'    => ['type' => 'string', 'enum' => $cats, 'description' => 'Optional. e.g. bedroom, kitchen, pool, bathroom, exterior, view, living'],
                    'max'         => ['type' => 'integer', 'minimum' => 1, 'maximum' => 4, 'description' => 'How many to send (default 3)'],
                ],
                'required' => ['property_id'],
            ],
        );
    }

    public function execute(array $args, ToolContext $ctx): array
    {
        $propertyId = (int) ($args['property_id'] ?? 0);
        $category   = $args['category'] ?? null;
        $max        = min(
            (int) ($args['max'] ?? 3),
            (int) config('agent.max_photos_per_reply', 4),
        );

        $q = PropertyPhoto::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $ctx->tenant->id)
            ->where('property_id', $propertyId);

        if ($category) {
            $q->where('category', $category);
        }

        $photos = $q->orderByDesc('is_hero')->orderBy('sort_order')->limit($max)->get();

        if ($photos->isEmpty()) {
            return [
                'sent'    => 0,
                'message' => $category
                    ? "No photos in category '{$category}' for this property."
                    : 'No photos uploaded yet for this property.',
            ];
        }

        $sent = 0;
        foreach ($photos as $photo) {
            $caption = $ctx->locale === 'ms'
                ? ($photo->caption_bm ?: $photo->caption_en ?: '')
                : ($photo->caption_en ?: $photo->caption_bm ?: '');

            $msg = WhatsappMessenger::dispatchAgentReply(
                tenant:         $ctx->tenant,
                recipientPhone: $ctx->conversation->guest_phone,
                body:           $caption !== '' ? $caption : ' ',
                media: [
                    'url'      => $photo->url(),
                    'kind'     => 'image',
                    'filename' => basename($photo->path),
                ],
            );
            if ($msg) $sent++;
        }

        return [
            'sent'        => $sent,
            'category'    => $category,
            'property_id' => $propertyId,
            'message'     => "Sent {$sent} photo(s)" . ($category ? " (category: {$category})" : '') . '.',
        ];
    }
}
