<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Ast\Nodes\Interfaces;

interface NamedNodeInterface extends AstNodeInterface
{
    /**
     * NODE TYPE NAME (literal, group, sequence, subsequence ... eg)
     * @return string
     */
    public function getNodeType(): string;
}
