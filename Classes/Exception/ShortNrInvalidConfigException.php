<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Exception;

/**
 * The YAML config could not be built. Already logged once and remembered in the config cache
 * until a YAML file changes or caches are flushed; callers must not log it again.
 */
final class ShortNrInvalidConfigException extends ShortNrConfigException
{
}
