<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Tests\Integration\Support;

use CPSIT\ShortNr\Cache\CacheManager;

/**
 * Records which tags get flushed (on FileBackend every flushByTag reads every cache file).
 */
final class TagRecordingCacheManager extends CacheManager
{
    /** @var list<string> */
    public array $flushedTags = [];

    public function invalidateByTag(string $tag): void
    {
        $this->flushedTags[] = $tag;
        parent::invalidateByTag($tag);
    }
}
