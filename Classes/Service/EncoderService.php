<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service;

use CPSIT\ShortNr\Exception\ShortNrCacheException;
use CPSIT\ShortNr\Exception\ShortNrConfigException;
use CPSIT\ShortNr\Exception\ShortNrDemandNormalizationException;
use CPSIT\ShortNr\Service\Url\Demand\EncoderDemandInterface;
use CPSIT\ShortNr\Service\Url\EncodingDemandNormalizer\EncodingDemandNormalizationService;

/**
 * TODO: respect Systems with Overlay Pages and systems with Single language per Tree system (MultiTree))
 */
class EncoderService extends AbstractUrlService
{
    public function __construct(
        private readonly EncodingDemandNormalizationService $demandNormalizationService
    )
    {}

    /**
     * @param EncoderDemandInterface $demand
     * @return string|null
     * @throws ShortNrDemandNormalizationException
     * @throws ShortNrCacheException
     * @throws ShortNrConfigException
     */
    public function encode(EncoderDemandInterface $demand): ?string
    {
        /*
         * 1. find the ConfigItem and the UID of the $demand (CHECK!)
         * 2. normalize UID if needed (we only use base UIDs (on language Overlay systems, and if ConfigItem allow languageOverlay))
         * 3. create an encoding context with the matchGroup values that are needed
         * 4. Reverse generate via Regex the ShortNr
         * 5. cache the shortNr
         * 6. return the shortNr
         */

        // normalize Demand
        $demand->setNormalizationResult(
            $this->demandNormalizationService->normalize($demand, $this->getConfig())
        );

        return null;
    }
}
