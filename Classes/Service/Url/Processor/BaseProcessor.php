<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Processor;

use CPSIT\ShortNr\Domain\Repository\ShortNrRepository;

abstract class BaseProcessor implements ProcessorInterface
{
    public function __construct(
        protected readonly ShortNrRepository $shortNrRepository
    )
    {}

    protected function mapCondition(array $condition, array $matches): array
    {
        $result = [];
        foreach ($condition as $key => $value) {
            // support only match by now, performance reasons
            if (preg_match('/{match-(\d+)}/', (string)$value, $m)) {
                $index = (int)($m[1] ?? -1);
                $matchValue = $matches[$index][0] ?? null;
                $result[$key] = $matchValue !== null ? (int)$matchValue : null;
            } else {
                $result[$key] = $value;
            }
        }

        return array_filter($result, fn($value) => $value !== null);
    }
}
