<?php
declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Condition;

use CPSIT\ShortNr\Service\Url\Condition\DTO\ConfigMatchCandidate;

/**
 * Replaces or strips placeholders that appear inside a condition array.
 */
final class PlaceholderResolver
{
    public function replace(array $condition, ConfigMatchCandidate $candidate): array
    {
        return $this->walk($condition, $candidate, false);
    }

    public function strip(array $condition, ConfigMatchCandidate $candidate): array
    {
        return $this->walk($condition, $candidate, true);
    }

    /**
     * Generic recursive walk.
     *
     * @param array $array
     * @param ConfigMatchCandidate $candidate
     * @param bool $removeIfUnresolved  TRUE = drop key, FALSE = keep original
     * @return array
     */
    private function walk(array $array, ConfigMatchCandidate $candidate, bool $removeIfUnresolved): array
    {
        foreach ($array as $k => &$v) {
            if (is_array($v)) {
                $v = $this->walk($v, $candidate, $removeIfUnresolved);
                if ($v === []) {                 // prune empty branches
                    unset($array[$k]);
                }
                continue;
            }

            if (!is_string($v) || !$candidate->isMatchGroupString($v)) {
                continue;
            }

            $resolved = $candidate->getValueFromMatchesViaMatchGroupString($v);

            if ($resolved !== null && $resolved !== false) {
                $v = $resolved;                 // happy path: value replaced
                continue;
            }

            if ($removeIfUnresolved) {
                unset($array[$k]);              // strip mode: drop the key
            }
            // else: keep original placeholder (replace mode)
        }
        unset($v);

        return $array;
    }
}
