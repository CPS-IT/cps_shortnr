<?php declare(strict_types=1);

namespace CPSIT\ShortNr\EventListener;

use CPSIT\ShortNr\Config\ConfigLoader;
use CPSIT\ShortNr\Config\DTO\ConfigInterface;
use CPSIT\ShortNr\Event\ShortNrDecodeConfigResolverEvent;
use CPSIT\ShortNr\Exception\ShortNrCacheException;
use CPSIT\ShortNr\Exception\ShortNrConfigException;
use CPSIT\ShortNr\Service\Url\Regex\MatchResult;
use CPSIT\ShortNr\Service\Url\Regex\RegexMatchProcessor;

class DecodeConfigResolverListener
{
    public function __construct(
        private readonly ConfigLoader $configLoader,
        private readonly RegexMatchProcessor $regexMatchProcessor
    )
    {}

    /**
     * @param ShortNrDecodeConfigResolverEvent $event
     * @return void
     * @throws ShortNrCacheException
     * @throws ShortNrConfigException
     */
    public function handleEvents(ShortNrDecodeConfigResolverEvent $event): void
    {
        $matchResult = $this->getRegexMatchesNameGroup($this->configLoader->getConfig(), $event->getDecoderDemand()->getShortNr());
        $event->setConfigItem($matchResult->getConfigItem());
        $event->setMatchResult($matchResult);
    }

    /**
     * @param ConfigInterface $config
     * @param string $shortNr
     * @return MatchResult|null
     * @throws ShortNrCacheException
     * @throws ShortNrConfigException
     */
    private function getRegexMatchesNameGroup(ConfigInterface $config, string $shortNr): ?MatchResult
    {
        $group = $this->configLoader->getConfig()->getUniqueRegexConfigNameGroup();
        foreach ($group as $regex => $configNames) {

            return $this->regexMatchProcessor->matchResult($regex, $shortNr, $config);
        }

        return null;
    }
}
