<?php

namespace Inspector\Symfony\Bundle;

class Filters
{
    public static function matchWithWildcard(string $pattern, string $url)
    {
        // Escape special regex characters in the pattern, except for '*'.
        $escapedPattern = preg_quote($pattern, '/');

        // Replace '*' in the pattern with '.*' for regex matching.
        $regex = '/^' . str_replace('\*', '.*', $escapedPattern) . '$/';

        // Perform regex match.
        return (bool)preg_match($regex, $url);
    }
}
