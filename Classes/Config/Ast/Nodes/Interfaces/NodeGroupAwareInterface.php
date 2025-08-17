<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Ast\Nodes\Interfaces;

interface NodeGroupAwareInterface
{
    /**
     * @return array collect all groupNames (g1, g2 ... so on)
     */
    public function getGroupNames(): array;
}
