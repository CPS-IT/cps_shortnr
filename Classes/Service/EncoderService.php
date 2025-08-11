<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service;

use CPSIT\ShortNr\Service\Url\Demand\EncoderDemandInterface;

/**
 * TODO: respect Systems with Overlay Pages and systems with Single language per Tree system (MultiTree))
 */
class EncoderService extends AbstractUrlService
{
    public function encode(EncoderDemandInterface $demand): ?string
    {
        return null;
    }
}
