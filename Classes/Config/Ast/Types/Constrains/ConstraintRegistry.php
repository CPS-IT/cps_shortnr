<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Ast\Types\Constrains;

use CPSIT\ShortNr\Config\Ast\Types\Constrains\Interfaces\TypeConstraint;
use CPSIT\ShortNr\Config\Ast\Types\ConstraintAwareInterface;
use CPSIT\ShortNr\Exception\ShortNrPatternConstraintException;

class ConstraintRegistry
{

    /**
     * @param array $constraints
     * @param ConstraintAwareInterface $type
     * @return array<string, TypeConstraint>
     * @throws ShortNrPatternConstraintException
     */
    public function generateConstraintsForType(array $constraints, ConstraintAwareInterface $type): array
    {
        $constraintsObj = [];
        $constraintMap = $this->getConstraintMap($type);
        foreach ($constraints as $name => $value) {
            /** @var  $constraintClass */
            $constraintClass = $constraintMap[$name] ?? throw new ShortNrPatternConstraintException('Constraint with name' . $name . ' not found for ' . $type::class . ', support only ('. implode(',', array_keys($constraintMap)) .')', '', '', '');
            $constraintsObj[$name] = new $constraintClass($value);
        }

        return $constraintsObj;
    }

    /**
     * @return array<string, TypeConstraint>
     * @throws ShortNrPatternConstraintException
     */
    private function getConstraintMap(ConstraintAwareInterface $type): array
    {
        $map = [];
        foreach ($type->getSupportedConstraintClasses() as $constraintClass) {
            if (is_a($constraintClass, TypeConstraint::class, true)) {
                if ($constraintClass::NAME === TypeConstraint::NAME || empty($constraintClass::NAME)) {
                    throw new ShortNrPatternConstraintException('Constraint ' . $constraintClass . ' from type ' . $type::class . ' has invalid name: ' . $constraintClass::NAME, '', '', '');
                }

                $map[$constraintClass::NAME] = $constraintClass;
            } else {
                throw new ShortNrPatternConstraintException('Constraint ' . $constraintClass . ' from type ' . $type::class . 'must implement ' . TypeConstraint::class, '', '', '');
            }
        }

        return $map;
    }
}
