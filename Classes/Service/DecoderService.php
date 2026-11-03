<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service;

use CPSIT\ShortNr\Cache\CacheTags;
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
    private const CACHE_LIFETIME = 604_800; // one week
    // a notFound answer is cheap to recompute and may be wrong after writes no hook sees
    // (plain SQL importers): short lifetime as a safety net
    private const NOT_FOUND_CACHE_LIFETIME = 3_600;

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
     * Cached per parsed short number. An entry lives at most a week, ends early when a record of the
     * answer (or a competing candidate) gets a scheduled start/end, and is dropped when an editor saves
     * a record of a candidate's table (ClearCacheDataHandlerHook).
     *
     * @param DecoderDemandInterface $demand
     * @return string|null null if no decoder url, string decoded url
     * @throws ShortNrCacheException
     */
    public function decode(DecoderDemandInterface $demand): ?string
    {
        $candidates = iterator_to_array($demand->getCandidates(), false);
        if ($demand->noCache() || $demand->getCacheKey() === null || empty($candidates)) {
            return $this->decodeWithDemand($demand)[0];
        }

        $cacheKey = $this->buildCacheKey($candidates);
        $cached = $this->getCacheManager()->get($cacheKey);
        if (is_string($cached)) {
            return $cached;
        }

        [$uri, $isNotFound] = $this->decodeWithDemand($demand);
        if (is_string($uri) && $uri !== '') {
            [$ttl, $tags] = $this->getCacheLifetimeAndTags($candidates);
            if ($isNotFound) {
                $ttl = min($ttl, self::NOT_FOUND_CACHE_LIFETIME);
            }
            $this->getCacheManager()->set($cacheKey, $uri, $ttl, $tags);
        }

        return $uri;
    }

    /**
     * Keyed by what the patterns parsed, not by the raw input: REC200, REC0200, REC00200 ... are one
     * entry, so bots cannot grow the cache (and with it the cost of every flush) without bounds.
     *
     * @param list<ConfigCandidateInterface> $candidates
     */
    private function buildCacheKey(array $candidates): string
    {
        $parsed = [];
        foreach ($candidates as $candidate) {
            $values = $candidate->getMatchResult()->toArray();
            unset($values['input']);
            $parsed[$candidate->getConfigItem()->getName()] = $values;
        }

        return 'decode-' . json_encode($parsed);
    }

    /**
     * @param list<ConfigCandidateInterface> $candidates
     * @return array{int, list<string>}
     */
    private function getCacheLifetimeAndTags(array $candidates): array
    {
        $now = (int)($GLOBALS['SIM_ACCESS_TIME'] ?? $GLOBALS['EXEC_TIME'] ?? time());
        $ttl = self::CACHE_LIFETIME;
        $tags = [CacheTags::ALL, CacheTags::URI, CacheTags::DECODE];
        foreach ($candidates as $candidate) {
            $configItem = $candidate->getConfigItem();
            // every candidate: a save on a losing candidate's table can change which one wins
            if ($table = $configItem->getTableName()) {
                $tags[] = CacheTags::forTable($table);
            }
            $nextChange = $this->languageOverlayService->getNextVisibilityChange($configItem, $candidate->getMatchResult(), $now);
            if ($nextChange !== null) {
                $ttl = min($ttl, $nextChange - $now);
            }
        }

        return [$ttl, array_values(array_unique($tags))];
    }

    /**
     *
     * @param DecoderDemandInterface $demand
     * @return array{string|null, bool} [uri (null: not ours, pass on), true if the uri is the notFound target]
     */
    private function decodeWithDemand(DecoderDemandInterface $demand): array
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
                return [$uri, false];
            }
            $anyCandidatesExecuted = true;
        }

        $this->logCandidateErrors($demand, $errors);

        // no candidate satisfied, process NotFound
        if ($anyCandidatesExecuted) {
            foreach ($demand->getCandidates() as $candidate) {
                $notFoundUri = $this->getNotFoundProcessor($candidate->getConfigItem())?->decode($candidate->getConfigItem(), $candidate->getMatchResult());
                if (!empty($notFoundUri)) {
                    return [$notFoundUri, true];
                }
            }

            // notFound "" (or none configured): not ours to answer, the TYPO3 stack renders its 404.
            // Throwing here made the middleware answer 500 for every hidden or unknown record.
            return [null, true];
        }

        // no candidate was processed at this point, give up
        return [null, false];
    }

    /**
     * Not found is the normal answer (unknown or unpublished record, bots probing number ranges):
     * debug only. A not found caused by something else (query error, broken processor config) and
     * every other exception is an error, logged without the stack trace: one entry per request must
     * stay readable, the trace holds no more than file and line here.
     *
     * @param list<Throwable> $errors
     */
    private function logCandidateErrors(DecoderDemandInterface $demand, array $errors): void
    {
        // let's be safe since that is user generated code injected into the logs
        $shortNr = htmlentities(strip_tags($demand->getShortNr()));
        foreach ($errors as $error) {
            $cause = $error instanceof ShortNrNotFoundException ? $error->getPrevious() : $error;
            if ($cause === null) {
                $this->logger->debug('[ShortNr] not found: ' . $error->getMessage(), ['shortNr' => $shortNr]);
                continue;
            }

            $this->logger->error('[ShortNr] ' . $cause->getMessage(), [
                'shortNr' => $shortNr,
                'exception' => $cause::class,
                'file' => $cause->getFile(),
                'line' => $cause->getLine(),
            ]);
        }
    }
}
