<?php

namespace App\Services\Parsers\Concerns;

use App\Enums\ResetTiming;

/**
 * Trait for parsing feature reset timing from D&D feature descriptions.
 *
 * Handles patterns like:
 * - "short or long rest" → SHORT_REST (most permissive)
 * - "finish a short rest" → SHORT_REST
 * - "finish a long rest" → LONG_REST
 * - "between long rests" → LONG_REST
 * - "once per long rest" → LONG_REST
 * - "at dawn" / "next dawn" → DAWN
 *
 * Priority: "short or long rest" is checked first since it's the most permissive
 * reset timing (resets on either rest type).
 */
trait ParsesRestTiming
{
    /**
     * Parse reset timing from feature description text.
     *
     * @param  string  $text  Feature description containing reset timing
     */
    protected function parseResetTiming(string $text): ?ResetTiming
    {
        $lowerText = strtolower($text);

        // Priority 1: "short or long rest" - resets on BOTH, track as short_rest
        // This is the most permissive timing so check first
        if (preg_match('/short\s+or\s+long\s+rest/', $lowerText)) {
            return ResetTiming::SHORT_REST;
        }

        // Priority 2: "short rest" without "long" in phrase
        // Patterns: "finish a short rest", "complete a short rest"
        if (preg_match('/(?:finish|complete)\s+a\s+short\s+rest/', $lowerText)) {
            return ResetTiming::SHORT_REST;
        }

        // Priority 3: Dawn-based timing
        // Patterns: "at dawn", "next dawn", "until the next dawn"
        if (preg_match('/(?:at|next|until\s+(?:the\s+)?next)\s+dawn/', $lowerText)) {
            return ResetTiming::DAWN;
        }

        // Priority 4: Long rest patterns
        // Patterns: "finish a long rest", "between long rests", "once per long rest"
        if (preg_match('/(?:finish|complete)\s+a\s+long\s+rest/', $lowerText)) {
            return ResetTiming::LONG_REST;
        }

        if (preg_match('/between\s+long\s+rests/', $lowerText)) {
            return ResetTiming::LONG_REST;
        }

        if (preg_match('/once\s+per\s+long\s+rest/', $lowerText)) {
            return ResetTiming::LONG_REST;
        }

        return null;
    }
}
