<?php declare(strict_types=1);

namespace CPSIT\ShortNr\EventListener;

use CPSIT\ShortNr\Config\ConfigLoader;
use CPSIT\ShortNr\Event\ShortNrVerifyRequestEvent;
use CPSIT\ShortNr\Exception\ShortNrCacheException;
use CPSIT\ShortNr\Exception\ShortNrConfigException;

class DecodeVerifyListenerListener
{
    public function __construct(
        private readonly ConfigLoader $configLoader
    )
    {}

    /**
     * @param ShortNrVerifyRequestEvent $event
     * @return void
     * @throws ShortNrCacheException
     * @throws ShortNrConfigException
     */
    public function handleEvents(ShortNrVerifyRequestEvent $event): void
    {
        $event->setIsShortNrRequest(
            $this->isValidShortNr($event->getDecoderDemand()->getShortNr())
        );
    }

    /**
     * @param string $shortNr
     * @return bool
     * @throws ShortNrCacheException
     * @throws ShortNrConfigException
     */
    public function isValidShortNr(string $shortNr): bool
    {
        // fast regex ONLY pre-compiled check like /(regex1|regex2|regex3)/
        $compiledRegex = $this->configLoader->getCompiledRegexForFastCheck();
        if ($compiledRegex === null) return false;

        return preg_match($compiledRegex, $shortNr) === 1;
    }
}
