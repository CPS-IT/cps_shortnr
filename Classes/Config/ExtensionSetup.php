<?php declare(strict_types=1);

namespace CPSIT\Shortnr\Config;

use TYPO3\CMS\Core\Cache\Backend\FileBackend;
use TYPO3\CMS\Core\Cache\Frontend\VariableFrontend;

class ExtensionSetup
{
    public const EXT_KEY = 'cps_shortnr'; // same as composer.json
    public const CACHE_KEY = self::EXT_KEY;

    /**
     * add here more config options
     * @return void
     */
    public static function setup(): void
    {
        self::registerCache();
    }

    /**
     * register cache to typo3 API
     *
     * @return void
     */
    private static function registerCache(): void
    {
        // graceful fill out missing parts but respect other people configs
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations'][static::CACHE_KEY] = array_merge(
            [
                'frontend' => VariableFrontend::class,
                'backend' => FileBackend::class,
                'groups' => ['system']
            ],
            ($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations'][static::CACHE_KEY] ?? [])
        );
    }
}
