<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Ast\Visitor;

use CPSIT\ShortNr\Config\Ast\Nodes\GroupNode;
use CPSIT\ShortNr\Config\Ast\Nodes\LiteralNode;
use CPSIT\ShortNr\Config\Ast\Nodes\SequenceNode;
use CPSIT\ShortNr\Config\Ast\Nodes\SubSequenceNode;

interface AstVisitorInterface
{
    public function visitGroup(GroupNode $node): void;
    public function visitLiteral(LiteralNode $node): void;
    public function visitSequence(SequenceNode $node): void;
    public function visitSubSequence(SubSequenceNode $node): void;
}