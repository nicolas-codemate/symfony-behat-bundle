<?php

declare(strict_types=1);

namespace Elbformat\SymfonyBehatBundle\Helper;

/**
 * Do a plain or regex compare, when starts with ~
 *
 * @author Hannes Giesenow <hannes.giesenow@elbformat.de>
 */
class StringCompare
{
    public function stringContains(string $haystack, string $needle): bool
    {
        if (str_starts_with($needle, '~')) {
            return (bool) preg_match('/'.substr($needle, 1).'/', $haystack);
        }
        return str_contains($haystack, $needle);
    }
}
