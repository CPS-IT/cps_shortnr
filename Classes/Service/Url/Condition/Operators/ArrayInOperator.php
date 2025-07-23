<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Condition\Operators;

use CPSIT\ShortNr\Service\Url\Condition\Operators\DTO\OperatorHistoryInterface;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use Doctrine\DBAL\ArrayParameterType;

class ArrayInOperator implements OperatorInterface
{
    /**
     * @param mixed $fieldConfig
     * @return bool
     */
    public function support(mixed $fieldConfig): bool
    {
        return is_array($fieldConfig) && array_is_list($fieldConfig);
    }

    /**
     * @param string $fieldName
     * @param mixed $fieldConfig
     * @param QueryBuilder $queryBuilder
     * @param OperatorHistoryInterface|null $parent
     * @return string
     */
    public function process(string $fieldName, mixed $fieldConfig, QueryBuilder $queryBuilder, ?OperatorHistoryInterface $parent): string
    {
        $type = $this->determineArrayType($fieldConfig);
        $placeholder = $queryBuilder->createNamedParameter($fieldConfig, $type);

        if ($parent && $parent->hasOperatorTypeInHistory(NotOperator::class)) {
            return $queryBuilder->expr()->notIn($fieldName, $placeholder);
        }

        return $queryBuilder->expr()->in($fieldName, $placeholder);
    }

    private function determineArrayType(array $values): int
    {
        if (empty($values)) {
            return ArrayParameterType::STRING;
        }

        $firstType = gettype($values[0]);
        // Check if all values have the same type
        foreach ($values as $value) {
            if (gettype($value) !== $firstType) {
                return ArrayParameterType::STRING; // Mixed types
            }
        }

        return match ($firstType) {
            'integer' => ArrayParameterType::INTEGER,
            default => ArrayParameterType::STRING
        };

    }
}
