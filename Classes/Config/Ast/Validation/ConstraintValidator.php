<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Ast\Validation;

use CPSIT\ShortNr\Config\Ast\Nodes\GroupNode;
use CPSIT\ShortNr\Config\Ast\Nodes\Interfaces\AstNodeInterface;
use CPSIT\ShortNr\Config\Ast\Nodes\Interfaces\NestedNodeInterface;
use CPSIT\ShortNr\Exception\ShortNrPatternException;

final class ConstraintValidator implements ValidatorInterface
{
    /**
     * @throws ShortNrPatternException
     */
    public function validate(AstNodeInterface $astNode): void
    {
        $this->validateConstraints($astNode);
    }

    /**
     * @throws ShortNrPatternException
     */
    private function validateConstraints(AstNodeInterface $node): void
    {
        if ($node instanceof GroupNode) {
            foreach ($node->getType()->getConstraintsArgument() as $key => $value) {
                if ($value === '' || $value === null) {
                    throw new ShortNrPatternException(
                        "Empty constraint value for '$key' in group '".$node->getName()."'"
                    );
                }
            }
        }
        
        if ($node instanceof NestedNodeInterface) {
            foreach ($node->getChildren() as $child) {
                $this->validateConstraints($child);
            }
        }
    }
}
