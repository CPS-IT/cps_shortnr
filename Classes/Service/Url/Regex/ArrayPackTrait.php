<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Regex;

trait ArrayPackTrait
{
    /**
     * Recursively flatten *values only*.
     * (unchanged public API)
     */
    public function flatArray(iterable $input): iterable
    {
        foreach ($input as $item) {
            if (is_iterable($item)) {
                yield from $this->flatArray($item);
                continue;
            }
            yield $item;
        }
    }

    /**
     * Flatten an array to dot-notation keys.
     * Numeric indices are kept as-is (int|string).
     *
     * @param array<int|string,mixed> $array
     */
    private function flattenArrayKeyPath(
        array  $array,
        string $prefix = '',
        string $keyPathSeparator = '.'
    ): array
    {
        $result = [];

        foreach ($array as $k => $v) {
            // Escape separator inside key itself (URL-style)
            $safeKey = str_replace(
                $keyPathSeparator,
                rawurlencode($keyPathSeparator),
                (string)$k
            );

            $path = $prefix === ''
                ? $safeKey
                : $prefix . $keyPathSeparator . $safeKey;

            if (is_array($v) && $v !== []) {           // recurse
                $result += $this->flattenArrayKeyPath($v, $path, $keyPathSeparator);
            } else {                                   // leaf
                $result[$path] = $v;
            }
        }

        return $result;
    }

    /**
     * Re-expand a previously flattened array.
     *
     * @param array<string,mixed> $flatArray
     * @return array<int|string,mixed>
     */
    private function reconstructFlattenArrayKeyPath(
        array  $flatArray,
        string $keyPathSeparator = '.'
    ): array
    {
        $result = [];

        foreach ($flatArray as $compositeKey => $value) {
            $keys = explode($keyPathSeparator, $compositeKey);
            $cursor = &$result;

            foreach ($keys as $encodedKey) {
                // Undo URL encoding
                $key = rawurldecode($encodedKey);

                // Detect list vs assoc on-the-fly
                if (!isset($cursor[$key]) || !is_array($cursor[$key])) {
                    $cursor[$key] = [];
                }
                $cursor = &$cursor[$key];
            }
            $cursor = $value;
        }

        return $result;
    }
}
