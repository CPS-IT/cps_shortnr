<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Ast\Nodes;

class SequenceNode extends NestedAstNode
{
    public function generate(array $values): string
    {
        $result = '';
        foreach ($this->children as $child) {
            $result .= $child->generate($values);
        }
        return $result;
    }

    public function hasOptional(): bool
    {
        foreach ($this->children as $child) {
            if ($child->hasOptional()) {
                return true;
            }
        }
        return false;
    }

    public function getNodeType(): string
    {
        return 'sequence';
    }
}
