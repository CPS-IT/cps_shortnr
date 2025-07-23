<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Condition\Operators;

use CPSIT\ShortNr\Service\Url\Condition\Operators\DTO\OperatorHistoryInterface;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;

class RegexMatchOperator implements OperatorInterface
{
    /**
     * @param mixed $fieldConfig
     * @return bool
     */
    public function support(mixed $fieldConfig): bool
    {
        return is_array($fieldConfig) && array_key_exists('match', $fieldConfig);
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
        $pattern = $fieldConfig['match'];
        $param = $queryBuilder->createNamedParameter($pattern);

        $platformName = $queryBuilder->getConnection()->getDatabasePlatform()->getName();

        $regexOperator = match ($platformName) {
            'mysql', 'mariadb' => 'REGEXP',
            'postgresql', 'pdo_postgresql' => '~',
            'sqlite', 'sqlite3', 'pdo_sqlite' => 'REGEXP',
            default => 'REGEXP'
        };

        $notRegexOperator = match ($platformName) {
            'mysql', 'mariadb' => 'NOT REGEXP',
            'postgresql', 'pdo_postgresql' => '!~',
            'sqlite', 'sqlite3', 'pdo_sqlite' => 'NOT REGEXP',
            default => 'NOT REGEXP'
        };

        if ($parent && $parent->hasOperatorTypeInHistory(NotOperator::class)) {
            return $queryBuilder->expr()->comparison($fieldName, $notRegexOperator, $param);
        }

        return $queryBuilder->expr()->comparison($fieldName, $regexOperator, $param);
    }
}
