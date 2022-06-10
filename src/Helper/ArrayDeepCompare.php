<?php
declare(strict_types=1);

namespace Elbformat\SymfonyBehatBundle\Helper;

use DomainException;

/**
 * Helper to find the difference in two complex array structures.
 *
 * @author Hannes Giesenow <hannes.giesenow@elbformat.de>
 */
class ArrayDeepCompare
{
    protected ?string $difference = null;

    public function arrayEquals(array $a, array $b): bool
    {
        return !$this->hasDiff($a, $b);
    }

    public function arrayContains($container, $containment): bool
    {
        return !$this->hasDiff($containment, $container, '', false);
    }

    public function getDifference(): string
    {
        if (null === $this->difference) {
            throw new DomainException('No difference');
        }

        return $this->difference;
    }

    protected function arrayIsAssoc(array $arr)
    {
        if ([] === $arr) {
            return false;
        }

        return array_keys($arr) !== range(0, \count($arr) - 1);
    }

    /**
     * Check if two values/arrays are equal.
     *
     * @throws DomainException when differs
     */
    protected function hasDiff($a, $b, string $path = '', bool $reverseCheck = true): bool
    {
        if (!\is_array($a) && !\is_array($b)) {
            // Scalar values -> compare
            if ($a !== $b) {
                $this->difference = sprintf('%s: (%s) %s != (%s) %s', $path, \gettype($a), $a ?? '', \gettype($b), $b ?? '');

                return true;
            }

            return false;
        }

        if (!\is_array($a) || !\is_array($b)) {
            // One array -> mismatch
            $this->difference = sprintf('%s: <%s> != <%s>', $path, \gettype($a), \gettype($b));

            return true;
        }

        // Only two arrays left
        $isAssoc = $this->arrayIsAssoc($a);
        foreach ($a as $k => $v) {
            $subpath = ($path ? $path.'.' : '').$k;
            if ($isAssoc) {
                // Key does not exist in b
                if (!\array_key_exists($k, $b)) {
                    $this->difference = sprintf('%s: Missing', $subpath);

                    return true;
                }

                // Recurse check of values
                if ($this->hasDiff($v, $b[$k], $subpath, $reverseCheck)) {
                    return true;
                }

                // This key is done
                unset($b[$k]);
            } else {
                foreach ($b as $bVal) {
                    if (!$this->hasDiff($v, $bVal, $subpath, $reverseCheck)) {
                        continue 2;
                    }
                }
                $this->difference = sprintf('%s: %s Missing', $subpath, $v);

                return true;
            }
        }

        // Still entries left in b? -> unequal
        if ($reverseCheck && \count($b)) {
            foreach ($b as $k => $v) {
                $this->difference = sprintf('%s: Extra', $path.'/'.$k);

                return true;
            }
        }

        return false;
    }
}
