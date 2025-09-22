<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service;

use CPSIT\ShortNr\Exception\ShortNrCacheException;
use CPSIT\ShortNr\Exception\ShortNrConfigException;
use CPSIT\ShortNr\Exception\ShortNrNotFoundException;
use CPSIT\ShortNr\Service\Language\LanguageOverlayService;
use CPSIT\ShortNr\Service\Url\Demand\Decode\DecoderDemandInterface;
use CPSIT\ShortNr\Service\Url\Demand\Decode\RequestDecoderDemand;
use CPSIT\ShortNr\Service\Url\Demand\ConfigCandidate;
use CPSIT\ShortNr\Service\Url\Demand\ConfigCandidateInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Throwable;
use Generator;

class DecoderService extends AbstractUrlService
{
    public function __construct(
        private readonly LanguageOverlayService $languageOverlayService,
        private readonly LoggerInterface $logger,
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

            foreach ($this->getConfigItem($demand->getShortNr()) as $candidate) {
                $demand->addConfigCandidate($candidate);
            }

            return $demand;
        }

        return null;
    }

    /**
     * @param string $shortNr
     * @return Generator<ConfigCandidateInterface>
     */
    private function getConfigItem(string $shortNr): Generator
    {
        try {
            $config = $this->getConfig();
            foreach ($config->getPatterns() as $configName => $compiledPattern) {
                $matchResult = $compiledPattern->match($shortNr);
                if ($matchResult && !$matchResult->isFailed()) {
                    yield new ConfigCandidate($config->getConfigItem($configName), $matchResult);
                }
            }
        } catch (ShortNrCacheException|ShortNrConfigException) {
        }
    }

    /**
     * validate the demand if that is for us to parse or not.
     * @param DecoderDemandInterface $demand
     * @return bool
     */
    private function isValid(DecoderDemandInterface $demand): bool
    {
        // default browser escape
        if ($demand->getShortNr() === 'favicon.ico')
            return false;

        try {
            return $this->getConfigLoader()->getHeuristicPattern()->support($demand->getShortNr());
        } catch (Throwable) {
            return false;
        }
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
        return $this->getCacheManager()->getType3CacheValue(
            sprintf('decode-%s', $demand->getShortNr()),
            fn() => $this->decodeDemand($demand),
            ttl: 604_800, // one week
            tags: ['all', 'uri', 'decode']
        );
    }

    /**
     *
     * @param DecoderDemandInterface $demand
     * @return string|null null if no decoder url, string decoded url
     * @throws ShortNrNotFoundException
     */
    private function decodeDemand(DecoderDemandInterface $demand): ?string
    {
        $anyCandidatesExecuted = false;
        $errors = [];
        // try to match candidates, one at a time, to resolve the match via config to an uri
        foreach ($demand->getCandidates() as $candidate) {
            $configItem = $candidate->getConfigItem();
            $matchResult = $candidate->getMatchResult();
            try {
                if ($configItem->canLanguageOverlay()) {
                    // replace to the needed UID
                    $matchResult = $this->languageOverlayService->resolveLanguageOverlay($configItem, $matchResult);
                }
                $uri = $this->getProcessor($configItem)?->decode($configItem, $matchResult);
            } catch (Throwable $e) {
                $errors[] = $e;
                $uri = null;
            }

            // candidate gives us a valid answer, get the first one we can find
            if (!empty($uri)) {
                return $uri;
            }
            $anyCandidatesExecuted = true;
        }

        // log errors
        foreach ($errors as $error) {
            $this->logger->error('[ShortNr] '.$error->getMessage(), [
                // let's be safe since that is user generated code injected into the logs
                'shortNr' => htmlentities(strip_tags($demand->getShortNr())),
                'line' => $error->getLine(),
                'file' => $error->getFile(),
                'trace' => $error->getTrace()
            ]);
        }

        // no candidate satisfied, process NotFound
        if ($anyCandidatesExecuted) {
            foreach ($demand->getCandidates() as $candidate) {
                $notFoundUri = $this->getNotFoundProcessor($candidate->getConfigItem())?->decode($candidate->getConfigItem(), $candidate->getMatchResult());
                if (!empty($notFoundUri)) {
                    return $notFoundUri;
                }
            }

            // no NotFound config could be processed, fatal exception
            throw new ShortNrNotFoundException('Could not match ANY pattern, invalid config?');
        }

        // no candidate was processed at this point, give up
        return null;
    }
}
