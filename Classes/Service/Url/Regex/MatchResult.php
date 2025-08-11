<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Regex;

use CPSIT\ShortNr\Config\DTO\FieldConditionInterface;
use CPSIT\ShortNr\Config\DTO\ConfigItemInterface;
use CPSIT\ShortNr\Config\DTO\FieldCondition;

class MatchResult
{
    use ArrayPackTrait;

    // predefined static matches
    private const MATCH_LIST = [
        '{match-1}' => 1,
        '{match-2}' => 2,
        '{match-3}' => 3,
        '{match-4}' => 4,
        '{match-5}' => 5,
        '{match-6}' => 6,
        '{match-7}' => 7,
        '{match-8}' => 8,
        '{match-9}' => 9,
        '{match-10}' => 10
    ];

    private array $cache = [];
    private ?array $matchPlaceholderList = null;

    private const MATCH_PREFIX_PLACEHOLDER = '{match-';
    public const DEFAULT_MATCH_REGEX = '/'. self::MATCH_PREFIX_PLACEHOLDER .'(\d+)}/';

    /**
     * @param ConfigItemInterface $configItem
     * @param array $matches
     */
    public function __construct(
        private readonly ConfigItemInterface $configItem,
        private readonly array               $matches
    )
    {
        $this->getMatchPlaceholderList();
    }

    /**
     * @return ConfigItemInterface
     */
    public function getConfigItem(): ConfigItemInterface
    {
        return $this->configItem;
    }

    /**
     * @return bool
     */
    public function isValid(): bool
    {
        if (empty($this->matches)) {
            return false;
        }

        return $this->cache['isValid'] ??= $this->didConfigItemMatch();
    }

    /**
     * @return string
     */
    public function getPrefix(): string
    {
        return $this->extractValueFromMatches($this->configItem->getPrefixMatch());
    }

    public function getIdentifierFieldCondition(): ?FieldConditionInterface
    {
        return $this->getFieldCondition($this->configItem->getRecordIdentifier() ?? '');
    }

    public function getLanguageFieldCondition(): ?FieldConditionInterface
    {
        return $this->getFieldCondition($this->configItem->getLanguageField() ?? '');
    }

    public function getFieldCondition(string $fieldName): ?FieldConditionInterface
    {
        return $this->getMatchPlaceholderList()[$fieldName] ?? null;
    }

    /**
     * @return array
     */
    private function getMatchPlaceholderList(): array
    {
        return $this->matchPlaceholderList ??= $this->generateMatchPlaceholderList();
    }

    /**
     * @return array
     */
    private function generateMatchPlaceholderList(): array
    {
        $list = [];
        foreach ($this->configItem->getCondition() as $fieldConditionObject) {
            $condition = $fieldConditionObject->getRawCondition();
            if (is_iterable($condition)) {
                foreach ($this->flattenArrayKeyPath(iterator_to_array($condition)) as $path => $value) {
                    if (is_string($value) && $this->isPlaceholder($value)) {
                        // handle it so that multiple paths are good resolved
                        $fieldConditionObject->addMatch((string)$path, $this->getMatchIndex($value), $this->extractValueFromMatches($value));
                        $list[$fieldConditionObject->getFieldName()] = $fieldConditionObject;
                    }
                }
            } else {
                if ($this->isPlaceholder($condition)) {
                    $fieldConditionObject->addMatch(null, $this->getMatchIndex($condition), $this->extractValueFromMatches($condition));
                    $list[$fieldConditionObject->getFieldName()] = $fieldConditionObject;
                }
            }
        }

        return $list;
    }

    /**
     * @return bool
     */
    private function didConfigItemMatch(): bool
    {
        $prefix = $this->getPrefix();
        return strtolower($prefix) === strtolower($this->configItem->getPrefix());
    }

    /**
     * @param string $placeholder
     * @return mixed
     */
    private function extractValueFromMatches(string $placeholder): mixed
    {
        if (isset($this->cache['placeholder'][$placeholder])) {
            return $this->cache['placeholder'][$placeholder];
        }

        if (!$this->isPlaceholder($placeholder)) {
            return $this->cache['placeholder'][$placeholder] = $placeholder; // Literal value
        }

        $index = $this->getMatchIndex($placeholder);
        return $this->cache['placeholder'][$placeholder] = ($this->matches[$index][0] ?? null);
    }

    /**
     * @param string $value
     * @return bool
     */
    private function isPlaceholder(string $value): bool
    {
        return isset(self::MATCH_LIST[$value]) || (str_starts_with($value, self::MATCH_PREFIX_PLACEHOLDER) && str_ends_with($value, '}'));
    }

    /**
     * @param string $placeholder
     * @return int|null
     */
    private function getMatchIndex(string $placeholder): ?int
    {
        // use the predefined map to fast resolve
        if (isset(self::MATCH_LIST[$placeholder])) {
            return self::MATCH_LIST[$placeholder];
        }

        // fallback, process the regex
        if (preg_match(self::DEFAULT_MATCH_REGEX, $placeholder, $matches)) {
            return (int)$matches[1];
        }

        return null;
    }
}
