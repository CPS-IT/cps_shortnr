<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Regex;

use CPSIT\ShortNr\Config\DTO\ConfigInterface;

class RegexMatchProcessor
{
    /**
     * @param string $regex
     * @param string $shortNr
     * @param ConfigInterface $config
     * @return MatchResult|null
     */
    public function matchResult(string $regex, string $shortNr, ConfigInterface $config): ?MatchResult
    {
        if (preg_match($regex, $shortNr, $matches, PREG_OFFSET_CAPTURE) !== false) {
            foreach ($config->getConfigItems() as $configItem) {
                $result = new MatchResult($configItem, $matches);
                if ($result->isValid()) {
                    return $result;
                }
            }

            return null;
        }

        return null;
    }
}
