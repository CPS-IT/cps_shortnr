<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Traits;

use Symfony\Component\Filesystem\Path;

trait ValidateUriTrait
{
    private function validateUri(string $uri): bool
    {
        if(empty($uri = trim($uri))) {
            return false;
        }

        // Prepend a fake domain if the URI does not start with a scheme
        if (!preg_match('/^[a-zA-Z]+:\/\//', $uri)) {
            $uri = Path::join('https://short-nr.com', $uri);
        }

        return filter_var($uri, FILTER_VALIDATE_URL) !== false;
    }
}
