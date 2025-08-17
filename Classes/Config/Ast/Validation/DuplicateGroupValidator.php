<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Ast\Validation;

use CPSIT\ShortNr\Config\Ast\Nodes\GroupNode;
use CPSIT\ShortNr\Config\Ast\Nodes\Interfaces\AstNodeInterface;
use CPSIT\ShortNr\Config\Ast\Nodes\Interfaces\NestedNodeInterface;
use CPSIT\ShortNr\Exception\ShortNrPatternException;

final class DuplicateGroupValidator implements ValidatorInterface
{
    /**
     * @throws ShortNrPatternException
     */
    public function validate(AstNodeInterface $astNode): void
    {
        $groupNames = [];
        $this->collectGroupNames($astNode, $groupNames);
        
        $duplicates = array_filter(array_count_values($groupNames), fn($count) => $count > 1);
        
        if (!empty($duplicates)) {
            $duplicateNames = array_keys($duplicates);
            throw new ShortNrPatternException(
                'Duplicate group names found: ' . implode(', ', $duplicateNames)
            );
        }
    }
    
    private function collectGroupNames(AstNodeInterface $node, array &$groupNames): void
    {
        if ($node instanceof GroupNode) {
            $groupNames[] = $node->getName();
        }
        
        if ($node instanceof NestedNodeInterface) {
            foreach ($node->getChildren() as $child) {
                $this->collectGroupNames($child, $groupNames);
            }
        }
    }
}
