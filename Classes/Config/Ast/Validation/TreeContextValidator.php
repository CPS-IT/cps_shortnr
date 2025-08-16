<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Ast\Validation;

use CPSIT\ShortNr\Config\Ast\Nodes\Interfaces\AstNodeInterface;
use CPSIT\ShortNr\Config\Ast\Nodes\Interfaces\NestedNodeInterface;

/**
 * Validates tree context rules after all parent-child relationships are established.
 * Delegates to each node's validateTreeContext() method.
 */
final class TreeContextValidator implements ValidatorInterface
{
    public function validate(AstNodeInterface $rootNode): void
    {
        $this->validateNode($rootNode);
    }

    private function validateNode(AstNodeInterface $node): void
    {
        // Validate this node's context
        $node->validateTreeContext();
        
        // Recursively validate children
        if ($node instanceof NestedNodeInterface) {
            foreach ($node->getChildren() as $child) {
                $this->validateNode($child);
            }
        }
    }
}