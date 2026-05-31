<?php

namespace App\Services\Agent;

/**
 * Defensive output sanitizer for agent replies.
 *
 * WhatsApp uses its own formatting dialect — *bold*, _italic_, ~strike~,
 * ```mono``` — and does NOT render Markdown tables, **double-asterisk
 * bold**, # headings, or --- horizontal rules. Less capable LLMs (we've
 * seen this with DeepSeek v4 flash) ignore prompt instructions and emit
 * Markdown anyway. This class normalizes the text so guests see clean
 * formatting regardless of which model produced the reply.
 *
 * Pure function. No I/O. Safe to call multiple times (idempotent).
 */
class WhatsappFormatter
{
    public static function sanitize(string $text): string
    {
        $text = self::convertMarkdownTables($text);
        $text = self::convertDoubleAsteriskBold($text);
        $text = self::stripMarkdownHeadings($text);
        $text = self::stripHorizontalRules($text);
        $text = self::collapseBlankLines($text);
        return trim($text);
    }

    /**
     * Convert Markdown tables into "col — col" plain-text lines.
     * Drops the |---|---| separator row entirely.
     */
    private static function convertMarkdownTables(string $text): string
    {
        $lines = explode("\n", $text);
        $out = [];
        foreach ($lines as $line) {
            $t = trim($line);
            // Detect separator row: only |, -, :, spaces.
            if ($t !== '' && preg_match('/^\|?[\s\-:|]+\|?$/', $t) && str_contains($t, '-')) {
                continue;
            }
            // Detect a table row: starts and ends with |.
            if (preg_match('/^\|.+\|$/', $t)) {
                $cells = array_map('trim', explode('|', trim($t, '|')));
                $cells = array_filter($cells, fn ($c) => $c !== '');
                $out[] = implode(' — ', $cells);
                continue;
            }
            $out[] = $line;
        }
        return implode("\n", $out);
    }

    /**
     * **bold** → *bold*. WhatsApp uses single asterisks; double leaves
     * literal stars visible in the bubble.
     */
    private static function convertDoubleAsteriskBold(string $text): string
    {
        // Match **non-empty content** that doesn't span a newline.
        return preg_replace('/\*\*([^*\n]+?)\*\*/u', '*$1*', $text);
    }

    /**
     * Strip Markdown headings (`#`, `##`, `###` … at line start).
     * Keep the heading text — just remove the hash markers.
     */
    private static function stripMarkdownHeadings(string $text): string
    {
        return preg_replace('/^#{1,6}\s+/m', '', $text);
    }

    /**
     * Strip Markdown horizontal rules (`---`, `***`, `___` on their own line).
     */
    private static function stripHorizontalRules(string $text): string
    {
        return preg_replace('/^[\s]*[-*_]{3,}[\s]*$/m', '', $text);
    }

    /**
     * Collapse 3+ consecutive newlines to 2.
     */
    private static function collapseBlankLines(string $text): string
    {
        return preg_replace("/\n{3,}/", "\n\n", $text);
    }
}
