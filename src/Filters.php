<?php

declare(strict_types=1);

namespace Inspector\Symfony\Bundle;

use function preg_match;
use function preg_quote;
use function str_replace;

class Filters
{
    public static function matchWithWildcard(string $pattern, string $url): bool
    {
        // Escape special regex characters in the pattern, except for '*'.
        $escapedPattern = preg_quote($pattern, '/');

        // Replace '*' in the pattern with '.*' for regex matching.
        $regex = '/^' . str_replace('\*', '.*', $escapedPattern) . '$/';

        // Perform regex match.
        return (bool)preg_match($regex, $url);
    }
}
