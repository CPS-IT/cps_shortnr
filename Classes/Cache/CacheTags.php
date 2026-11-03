<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Cache;

/**
 * Tags of the cps_shortnr TYPO3 cache. Own prefix: the schema cache already uses bare table
 * names as tags, a record save must flush URIs, not the table schema.
 */
final class CacheTags
{
    public const ALL = 'all';
    public const URI = 'uri';
    public const DECODE = 'decode';
    public const ENCODE = 'encode';
    public const PAGE_TREE = 'tree';

    /**
     * every decode/encode result that read records of this table
     */
    public static function forTable(string $table): string
    {
        return 'shortnr_table_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $table);
    }
}
