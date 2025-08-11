<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service;

use CPSIT\ShortNr\Event\ShortNrBeforeProcessorDecodingEvent;
use CPSIT\ShortNr\Event\ShortNrDecodeConfigResolverEvent;
use CPSIT\ShortNr\Event\ShortNrUriFinishDecodingEvent;
use CPSIT\ShortNr\Event\ShortNrVerifyRequestEvent;
use CPSIT\ShortNr\Exception\ShortNrCacheException;
use CPSIT\ShortNr\Exception\ShortNrNotFoundException;
use CPSIT\ShortNr\Service\Url\Demand\DecoderDemand;
use CPSIT\ShortNr\Service\Url\Demand\DecoderDemandInterface;
use Psr\Http\Message\ServerRequestInterface;

class DecoderService extends AbstractUrlService
{
    /**
     * Check if that Request is a shortNr Request
     *
     * @param ServerRequestInterface $request
     * @return DecoderDemandInterface|null returns the decoder demand if valid shortNr otherwise NULL
     */
    public function getDecoderDemandFromRequest(ServerRequestInterface $request): ?DecoderDemandInterface
    {
        $event = $this->eventDispatcher->dispatch(new ShortNrVerifyRequestEvent($request, DecoderDemand::makeFromRequest($request)));
        if ($event instanceof ShortNrVerifyRequestEvent && $event->isShortNrRequest()) {
            return $event->getDecoderDemand();
        }

        return null;
    }

    /**
     * @param DecoderDemandInterface $demand
     * @return string|null null if no decoder url, string decoded url
     * @throws ShortNrCacheException
     * @throws ShortNrNotFoundException
     */
    public function decode(DecoderDemandInterface $demand): ?string
    {
        // cache for one day
        return $this->cacheManager->getType3CacheValue('decode_'.md5(strtolower($demand->getShortNr())), fn() => $this->decodeDemand($demand), 86_400);
    }

    /**
     *
     * @param DecoderDemandInterface $demand
     * @return string|null null if no decoder url, string decoded url
     * @throws ShortNrNotFoundException
     */
    private function decodeDemand(DecoderDemandInterface $demand): ?string
    {
        /** @var ShortNrDecodeConfigResolverEvent $event */
        $event = $this->eventDispatcher->dispatch(new ShortNrDecodeConfigResolverEvent($demand));
        $demand = $event->getDecoderDemand();
        $configItem = $event->getConfigItem();
        $matchResult = $event->getMatchResult();
        // early exit no config item found, so we give that request free for other middleware
        if ($configItem === null || $matchResult === null) {
            return null;
        }

        $demand->setConfigItem($configItem);
        $demand->setMatchResult($matchResult);

        // handle language overlay normalisation
        // if key information missing in the config for that item, overlay is disabled
        if ($configItem->canLanguageOverlay()) {
            // replace to the needed UID
            $this->languageOverlayService->resolveLanguageOverlay($matchResult);
        }

        $demand = $this->eventDispatcher->dispatch(new ShortNrBeforeProcessorDecodingEvent($demand))->getDemand();
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
        $event = $this->eventDispatcher->dispatch(new ShortNrUriFinishDecodingEvent($demand, $uri, $notFound));
        return $event->getUri();
    }
}
