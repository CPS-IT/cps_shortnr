<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Ast\Visitor;

interface VisitableInterface
{
    public function accept(AstVisitorInterface $visitor): void;
}