<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url;

use CPSIT\ShortNr\Config\ConfigInterface;
use CPSIT\ShortNr\Exception\ShortNrCacheException;
use CPSIT\ShortNr\Exception\ShortNrConfigException;
use Generator;
use Psr\Http\Message\ServerRequestInterface;

class DecoderService extends AbstractUrlService
{
    /**
     * @param ServerRequestInterface $request
     * @return string|null
     * @throws ShortNrCacheException
     * @throws ShortNrConfigException
     */
    public function decodeRequest(ServerRequestInterface $request): ?string
    {
        return $this->decode($request->getUri()->getPath());
    }

    /**
     * @param string $uri
     * @return string|null
     * @throws ShortNrCacheException
     * @throws ShortNrConfigException
     */
    public function decode(string $uri): ?string
    {
        $uri = $this->normalizeShortNrUri($uri);
        // cache for one day
        return $this->cacheManager->getType3CacheValue('decode_'.$uri, fn() => $this->decodeUri($uri), 86_400);
    }

    /**
     *
     * @param string $uri
     * @return string|null
     * @throws ShortNrCacheException
     * @throws ShortNrConfigException
     */
    private function decodeUri(string $uri): ?string
    {
        $candidates = $this->findConfigCandidates($uri);
        $config = $this->configLoader->getConfig();

        foreach ($candidates as $candidate) {
            $regexMatches = $candidate['matches'] ?? [];
            foreach ($candidate['names']??[] as $name) {

                // load processor if possible and gives the decode task over with all current available information
                $processor = $this->getProcessor($config->getType($name));
                $decodedUri = $processor?->decode($uri, $name, $config, $regexMatches);
                if ($decodedUri !== null) {
                    return $decodedUri;
                }
            }
        }

        return null;
    }

    /**
     * @param string $uri
     * @return Generator
     * @throws ShortNrCacheException
     * @throws ShortNrConfigException
     */
    private function findConfigCandidates(string $uri): Generator
    {
        $config = $this->configLoader->getConfig();
        // Find all potential route matches
        return $this->conditionService->findAllMatchConfigCandidates($uri, $config);
    }
}
