<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Tests\Integration\Support;

use CPSIT\ShortNr\Cache\CacheManager;

/**
 * Records which tags get flushed (on FileBackend every flushByTag reads every cache file) and
 * which entries get written.
 */
final class TagRecordingCacheManager extends CacheManager
{
    /** @var list<string> */
    public array $flushedTags = [];

    /** @var list<array{key: string, value: string|array, ttl: int, tags: list<string>}> */
    public array $written = [];

    public function invalidateByTag(string $tag): void
    {
        $this->flushedTags[] = $tag;
        parent::invalidateByTag($tag);
    }

    public function set(string $cacheKey, string|array $value, int $ttl, array $tags = []): void
    {
        $this->written[] = ['key' => $cacheKey, 'value' => $value, 'ttl' => $ttl, 'tags' => $tags];
        parent::set($cacheKey, $value, $ttl, $tags);
    }
}
