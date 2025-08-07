<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url;

use CPSIT\ShortNr\Exception\ShortNrCacheException;
use CPSIT\ShortNr\Exception\ShortNrConfigException;
use CPSIT\ShortNr\Exception\ShortNrNotFoundException;
use Psr\Http\Message\ServerRequestInterface;

class DecoderService extends AbstractUrlService
{
    /**
     * @param ServerRequestInterface $request
     * @return string|null null if no decoder url, string decoded url
     * @throws ShortNrCacheException
     * @throws ShortNrConfigException|ShortNrNotFoundException
     */
    public function decodeRequest(ServerRequestInterface $request): ?string
    {
        return $this->decode($request->getUri()->getPath());
    }

    /**
     * @param string $uri
     * @return string|null null if no decoder url, string decoded url
     * @throws ShortNrCacheException
     * @throws ShortNrConfigException|ShortNrNotFoundException
     */
    public function decode(string $uri): ?string
    {
        $shortNr = $this->normalizeShortNrUri($uri);
        // cache for one day
        return $this->cacheManager->getType3CacheValue('decode_'.md5(strtolower($shortNr)), fn() => $this->decodeShortNr($shortNr), 86_400);
    }

    /**
     *
     * @param string $shortNr
     * @return string|null null if no decoder url, string decoded url
     * @throws ShortNrCacheException
     * @throws ShortNrConfigException|ShortNrNotFoundException
     */
    private function decodeShortNr(string $shortNr): ?string
    {
        $config = $this->getConfig();
        $thrownNotFoundExceptionInConfigItem = [];
        foreach ($this->conditionService->findAllMatchConfigCandidates($shortNr, $config) as $candidate) {

            // extract the Prefix from the candidate using the config
            $configItem = $this->conditionService->getConfigItem($candidate, $config);
            if ($configItem === null) {
                continue;
            }

            try {
                $result = $this->getProcessor($configItem)?->decode($candidate, $configItem);
            } catch (ShortNrNotFoundException) {
                // handle not found only AT the end ... try other candidates too first
                $thrownNotFoundExceptionInConfigItem[$configItem->getName()] = [
                    'configItem' => $configItem,
                    'candidate' => $candidate,
                ];
                $result = null;
            }

            if ($result !== null) {
                return $result;
            }
        }

        if (!empty($thrownNotFoundExceptionInConfigItem)) {
            $firstKey = array_key_first($thrownNotFoundExceptionInConfigItem);;
            $configItem = $thrownNotFoundExceptionInConfigItem[$firstKey]['configItem'];
            $candidate = $thrownNotFoundExceptionInConfigItem[$firstKey]['candidate'];
            // NotFoundProcessor should not throw a ShortNrNotFoundException
            return $this->getNotFoundProcessor($configItem)?->decode($candidate, $configItem);
        }

        return null;
    }
}
