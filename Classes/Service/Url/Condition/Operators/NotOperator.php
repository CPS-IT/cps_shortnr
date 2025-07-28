<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Condition\Operators;

use CPSIT\ShortNr\Service\Url\Condition\Operators\DTO\OperatorHistory;
use CPSIT\ShortNr\Service\Url\Condition\Operators\DTO\OperatorHistoryInterface;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;

class NotOperator implements WrappingOperatorInterface
{
    /**
     * @param mixed $fieldConfig
     * @return bool
     */
    public function supports(mixed $fieldConfig): bool
    {
        return is_array($fieldConfig) && array_key_exists('not', $fieldConfig);
    }

    /**
     * @param string $fieldName
     * @param mixed $fieldConfig
     * @param QueryBuilder $queryBuilder
     * @param OperatorHistoryInterface|null $parent
     * @return mixed
     */
    public function process(string $fieldName, mixed $fieldConfig, QueryBuilder $queryBuilder, ?OperatorHistoryInterface $parent): mixed
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
     * @param string $fieldName
     * @param mixed $fieldConfig
     * @param QueryBuilder $queryBuilder
     * @param OperatorHistoryInterface|null $parent
     * @param callable $nestedCallback
     * @return array|null
     */
    public function wrap(string $fieldName, mixed $fieldConfig, QueryBuilder $queryBuilder, ?OperatorHistoryInterface $parent, callable $nestedCallback): ?array
    {
        if (!array_key_exists('not', $fieldConfig)) {
            return null;
        }

        return $nestedCallback($fieldName, $fieldConfig['not'], $queryBuilder,  new OperatorHistory($parent, $this));
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
        if (!array_key_exists('not', $fieldConfig)) {
            return null;
        }

        return $nestedCallback($fieldName, $fieldConfig['not'], $result,  new OperatorHistory($parent, $this));
    }
}
