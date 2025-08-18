<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Ast\Types\Constrains\Interfaces;

interface ModifyPatternAwareInterface
{
    /**
     * Modify the regex pattern based on this constraint.
     * Return the original pattern if no modification needed.
     *
     * @param string $basePattern The base regex pattern from the type
     * @return string The modified pattern
     */
    public function modifyPattern(string $basePattern): string;
}
