<?php

namespace App\Services;

class OcrCodeExtractor
{
    /** @param array{result?: mixed} $recognition */
    public function extract(string $type, array $recognition): array
    {
        $content = is_array($recognition['result'] ?? null)
            ? ($recognition['result']['content'] ?? null)
            : null;

        if (! is_string($content)) {
            return [];
        }

        $pattern = match ($type) {
            'carton' => '/(?<![A-Z0-9])(?:CTN[A-Z0-9]+|[0-9]{5,6})(?![A-Z0-9])/',
            'tpin' => '/(?<![A-Z0-9])(?!CTN)[A-Z0-9]{9}(?![A-Z0-9])/',
            default => null,
        };

        if ($pattern === null || preg_match_all($pattern, strtoupper($content), $matches) === false) {
            return [];
        }

        return array_values(array_unique($matches[0]));
    }
}
