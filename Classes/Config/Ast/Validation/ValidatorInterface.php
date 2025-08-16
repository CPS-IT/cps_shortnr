<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Ast\Validation;

use CPSIT\ShortNr\Config\Ast\Nodes\Interfaces\AstNodeInterface;

interface ValidatorInterface
{
    public function validate(AstNodeInterface $rootNode): void;
}