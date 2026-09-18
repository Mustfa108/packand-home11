<?php

namespace App\Support;

/**
 * Shared helpers for Gemini HTTP + JSON responses.
 */
final class GeminiHelpers
{
    /**
     * Strip API keys from exception/log messages that may include full request URLs.
     */
    public static function redactSecrets(string $message): string
    {
        $message = preg_replace('/([?&]key=)[^&\s"\']+/i', '$1***', $message) ?? $message;
        $message = preg_replace('/(AIza[0-9A-Za-z_-]{20,})/', '***', $message) ?? $message;
        $message = preg_replace('/(AQ\.[0-9A-Za-z_-]{20,})/', '***', $message) ?? $message;

        return $message;
    }

    /**
     * Normalize model text into JSON-decodable string: strip fences, control chars.
     */
    public static function sanitizeJsonText(string $responseText): string
    {
        $clean = trim($responseText);
        $clean = preg_replace('/^```(?:json)?\s*/i', '', $clean) ?? $clean;
        $clean = preg_replace('/\s*```$/', '', $clean) ?? $clean;
        $clean = trim($clean);

        // Remove ASCII control characters except tab/newline/carriage return.
        $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $clean) ?? $clean;

        if ($clean !== '' && ! str_starts_with($clean, '{') && ! str_starts_with($clean, '[')) {
            if (preg_match('/[\[{].*[\]}]/s', $clean, $matches)) {
                $clean = $matches[0];
            }
        }

        return trim($clean);
    }

    /**
     * Decode JSON after sanitization. Returns null on failure.
     *
     * @return array<mixed>|null
     */
    public static function decodeJson(string $responseText): ?array
    {
        $clean = self::sanitizeJsonText($responseText);

        try {
            $decoded = json_decode($clean, true, 512, JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $decoded : null;
        } catch (\Throwable) {
            // Attempt a light repair for truncated trailing commas.
            $repaired = preg_replace('/,\s*([}\]])/', '$1', $clean) ?? $clean;
            try {
                $decoded = json_decode($repaired, true, 512, JSON_THROW_ON_ERROR);

                return is_array($decoded) ? $decoded : null;
            } catch (\Throwable) {
                return null;
            }
        }
    }
}
