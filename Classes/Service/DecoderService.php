<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service;

use CPSIT\ShortNr\Event\ShortNrBeforeProcessorDecodingEvent;
use CPSIT\ShortNr\Event\ShortNrConfigItemProcessedEvent;
use CPSIT\ShortNr\Event\ShortNrUriFinishDecodingEvent;
use CPSIT\ShortNr\Exception\ShortNrCacheException;
use CPSIT\ShortNr\Exception\ShortNrConfigException;
use CPSIT\ShortNr\Exception\ShortNrNotFoundException;
use CPSIT\ShortNr\Service\Language\LanguageOverlayService;
use CPSIT\ShortNr\Service\Url\ConfigResolver\ConfigItemResolveProcessor;
use CPSIT\ShortNr\Service\Url\Demand\DecoderDemandInterface;
use CPSIT\ShortNr\Service\Url\Demand\RequestDecoderDemand;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

class DecoderService extends AbstractUrlService
{
    public function __construct(
        private readonly ConfigItemResolveProcessor $configItemResolveProcessor,
        private readonly LanguageOverlayService $languageOverlayService,
    )
    {}

    /**
     * Check if that Request is a shortNr Request
     *
     * @param ServerRequestInterface $request
     * @return DecoderDemandInterface|null returns the decoder demand if valid shortNr otherwise NULL
     */
    public function getDecoderDemandFromRequest(ServerRequestInterface $request): ?DecoderDemandInterface
    {
        $demand = RequestDecoderDemand::makeFromRequest($request);
        if ($this->isValid($demand)) {
            return $demand;
        }

        return null;
    }

    /**
     * validate the demand if that is for us to parse or not.
     * @param DecoderDemandInterface $demand
     * @return bool
     */
    private function isValid(DecoderDemandInterface $demand): bool
    {
        try {
            return $this->getConfigLoader()->getHeuristicPattern()->support($demand->getShortNr());
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * @param DecoderDemandInterface $demand
     * @return string|null null if no decoder url, string decoded url
     * @throws ShortNrCacheException
     * @throws ShortNrConfigException
     * @throws ShortNrNotFoundException
     */
    public function decode(DecoderDemandInterface $demand): ?string
    {
        // cache for one day
        return $this->getCacheManager()->getType3CacheValue('decode_'.md5(strtolower($demand->getShortNr())), fn() => $this->decodeDemand($demand), 86_400);
    }

    /**
     *
     * @param DecoderDemandInterface $demand
     * @return string|null null if no decoder url, string decoded url
     * @throws ShortNrCacheException
     * @throws ShortNrNotFoundException
     * @throws ShortNrConfigException
     */
    private function decodeDemand(DecoderDemandInterface $demand): ?string
    {
        $configItem = $this->configItemResolveProcessor->parseDecoderDemand($demand, $this->getConfig());
        // used to alter / manipulate or replace the configItem that will be used ... it contains all the Match and condition information
        $configItem = $this->getEventDispatcher()->dispatch(new ShortNrConfigItemProcessedEvent($demand, $configItem))->getConfigItem();

        // early exit no config item found, so we give that request free for other middleware
        if ($configItem === null) {
            return null;
        }

        $demand->setConfigItem($configItem);
        // handle language overlay normalisation
        // if key information missing in the config for that item, overlay is disabled
        if ($configItem->canLanguageOverlay()) {
            // replace to the needed UID
            $this->languageOverlayService->resolveLanguageOverlay($configItem);
        }

        $demand = $this->getEventDispatcher()->dispatch(new ShortNrBeforeProcessorDecodingEvent($demand))->getDemand();
        try {
            // update processor to use the new MatchResult and The new FieldCondition Value system
            $uri = $this->getProcessor($configItem)?->decode($demand);
            if (empty($uri)) {
                // empty URI are not allowed, at least a '/' must be there
                throw new ShortNrNotFoundException();
            }
            $notFound = false;
        } catch (ShortNrNotFoundException) {
            // not found fallback
            $uri = $this->getNotFoundProcessor($configItem)?->decode($demand);
            $notFound = true;
        }

        /** @var ShortNrUriFinishDecodingEvent $event */
        $event = $this->getEventDispatcher()->dispatch(new ShortNrUriFinishDecodingEvent($demand, $uri, $notFound));
        return $event->getUri();
    }
}
