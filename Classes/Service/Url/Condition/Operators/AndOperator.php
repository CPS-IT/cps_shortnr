<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Condition\Operators;

use CPSIT\ShortNr\Service\Url\Condition\Operators\DTO\OperatorHistory;
use CPSIT\ShortNr\Service\Url\Condition\Operators\DTO\OperatorHistoryInterface;
use TYPO3\CMS\Core\Database\Query\Expression\CompositeExpression;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;

class AndOperator implements WrappingOperatorInterface
{
    /**
     * @param mixed $fieldConfig
     * @return bool
     */
    public function supports(mixed $fieldConfig): bool
    {
        return is_array($fieldConfig) && count($fieldConfig) > 1 && !array_is_list($fieldConfig);
    }

    /**
     * @param string $fieldName
     * @param mixed $fieldConfig
     * @param QueryBuilder $queryBuilder
     * @param OperatorHistoryInterface|null $parent
     * @return null
     */
    public function process(string $fieldName, mixed $fieldConfig, QueryBuilder $queryBuilder, ?OperatorHistoryInterface $parent): null
    {
        return null;
    }

    /**
     * @param string $fieldName
     * @param mixed $fieldConfig
     * @param array $result
     * @param OperatorHistoryInterface|null $parent
     * @return array|null
     */
    public function postResultProcess(string $fieldName, mixed $fieldConfig, array $result, ?OperatorHistoryInterface $parent): ?array
    {
        return null;
    }

    /**
     * adds an and condition to the DB query
     *
     * @param string $fieldName
     * @param mixed $fieldConfig
     * @param QueryBuilder $queryBuilder
     * @param OperatorHistoryInterface|null $parent
     * @param callable $nestedCallback
     * @return null|CompositeExpression
     */
    public function wrap(string $fieldName, mixed $fieldConfig, QueryBuilder $queryBuilder, ?OperatorHistoryInterface $parent, callable $nestedCallback): ?CompositeExpression
    {
        // early exit with no deep branching here since its empty
        if (empty($fieldConfig)) {
            return null;
        }

        $andConditions = [];
        $operatorHistory = new OperatorHistory($parent, $this);
        foreach ($fieldConfig as $operatorName => $fieldConfigSegment) {
            $andConditions[] = $nestedCallback($fieldName, [$operatorName => $fieldConfigSegment], $queryBuilder, $operatorHistory);
        }

        $conditionsLeft = array_filter($andConditions);
        if (empty($conditionsLeft)) {
            return null;
        }

        return $queryBuilder->expr()->and(
            ...$conditionsLeft
        );
    }

    /**
     * filter Query Results based on the config
     *
     * @param string $fieldName
     * @param mixed $fieldConfig
     * @param array $result
     * @param OperatorHistoryInterface|null $parent
     * @param callable $nestedCallback
     * @return array|null
     */
    public function postResultWrap(string $fieldName, mixed $fieldConfig, array $result, ?OperatorHistoryInterface $parent, callable $nestedCallback): ?array
    {
        // Early return for empty config
        if (empty($fieldConfig)) {
            return $result;
        }

        // Create operator history once to reuse
        $operatorHistory = new OperatorHistory($parent, $this);
        foreach ($fieldConfig as $operatorName => $fieldConfigSegment) {
            $conditionResult = $nestedCallback($fieldName, [$operatorName => $fieldConfigSegment], $result, $operatorHistory);

            // In AND operation, if any condition is null/false, entire result is null
            if ($conditionResult === null) {
                return null;
            }
        }

        // All conditions passed
        return $result;
    }
}
