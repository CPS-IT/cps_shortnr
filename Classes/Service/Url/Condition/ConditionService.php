<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Condition;

use CPSIT\ShortNr\Config\ConfigInterface;

class ConditionService
{
    private array $cache = [];

    public function __construct(
        private readonly iterable $operators
    )
    {}

    /**
     * fast check if any given regex matches the uri
     *
     * @param string $uri
     * @param ConfigInterface $config
     * @return bool
     */
    public function matchAny(string $uri, ConfigInterface $config): bool
    {
        foreach ($config->getUniqueRegexConfigNameGroup() as $regex) {
            if (!empty($this->matchRegex($uri, $regex))) {
                return true;
            }
        }

        return false;
    }

    /**
     * gives the matches of the regex check
     *
     * @param string $uri
     * @param string $regex
     * @return array|null
     */
    private function matchRegex(string $uri, string $regex): ?array
    {
        if (isset($this->cache['match'][$uri])) {
            return $this->cache['match'][$uri];
        }

        $cleanUri = $uri;
        if (str_starts_with($uri,'/')) {
            $cleanUri = substr($uri, 1);
        }

        $matches = [];
        if (preg_match($regex, $cleanUri, $matches, PREG_OFFSET_CAPTURE)) {
            return $this->cache['match'][$uri] = $matches;
        }

        return $this->cache['match'][$uri] = null;
    }
}
