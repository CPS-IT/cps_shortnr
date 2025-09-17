<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\ConfigLoader;

use CPSIT\ShortNr\Exception\ShortNrConfigException;

class YamlConfigSanitizer
{
    private const MAX_STRING_LENGTH = 1000;

    /**
     * @param array $config
     * @return array
     * @throws ShortNrConfigException
     */
    public function sanitize(array $config): array
    {
        return array_map(function ($value) {
            return match (true) {
                is_string($value) => $this->sanitizeStringValue($value),
                is_array($value) => $this->sanitize($value),
                is_scalar($value) => $value,
                is_null($value) => null,
                default => throw new ShortNrConfigException('type \'' . gettype($value) . '\' in YAML are not supported')
            }
           ;
        }, $config);
    }

    /**
     * @param string $value
     * @return string
     * @throws ShortNrConfigException
     */
    private function sanitizeStringValue(string $value): string
    {
        // Remove null bytes (security)
        $value = str_replace("\0", '', $value);

        // Remove UTF-8 BOM
        $value = str_replace("\xEF\xBB\xBF", '', $value);

        // Optional: Remove other control characters except common ones
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value);

        // Always trim; maintainers can quote if they need the spaces
        $value = trim($value);

        // Basic length check to prevent DoS
        if (strlen($value) > self::MAX_STRING_LENGTH) { // More generous limit
            throw new ShortNrConfigException("Configuration value exceeds maximum length");
        }

        return $value;
    }
}
