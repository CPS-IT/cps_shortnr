<?php declare(strict_types=1);

namespace CPSIT\Shortnr\Tests\Unit\Config;

use CPSIT\Shortnr\Config\ExtensionSetup;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Cache\Backend\FileBackend;
use TYPO3\CMS\Core\Cache\Frontend\VariableFrontend;

class ExtensionSetupTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        unset($GLOBALS['TYPO3_CONF_VARS']);
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['TYPO3_CONF_VARS']);
        parent::tearDown();
    }

    public static function cacheConfigurationDataProvider(): array
    {
        return [
            'empty_cache_configuration' => [
                [],
                [
                    'frontend' => VariableFrontend::class,
                    'backend' => FileBackend::class,
                    'groups' => ['system']
                ]
            ],
            'no_cache_configuration_at_all' => [
                null,
                [
                    'frontend' => VariableFrontend::class,
                    'backend' => FileBackend::class,
                    'groups' => ['system']
                ]
            ],
            'partial_frontend_only' => [
                [
                    'frontend' => 'CustomFrontend'
                ],
                [
                    'frontend' => 'CustomFrontend',
                    'backend' => FileBackend::class,
                    'groups' => ['system']
                ]
            ],
            'partial_backend_only' => [
                [
                    'backend' => 'CustomBackend'
                ],
                [
                    'frontend' => VariableFrontend::class,
                    'backend' => 'CustomBackend',
                    'groups' => ['system']
                ]
            ],
            'partial_groups_only' => [
                [
                    'groups' => ['custom']
                ],
                [
                    'frontend' => VariableFrontend::class,
                    'backend' => FileBackend::class,
                    'groups' => ['custom']
                ]
            ],
            'partial_frontend_and_backend' => [
                [
                    'frontend' => 'CustomFrontend',
                    'backend' => 'CustomBackend'
                ],
                [
                    'frontend' => 'CustomFrontend',
                    'backend' => 'CustomBackend',
                    'groups' => ['system']
                ]
            ],
            'partial_frontend_and_groups' => [
                [
                    'frontend' => 'CustomFrontend',
                    'groups' => ['custom', 'pages']
                ],
                [
                    'frontend' => 'CustomFrontend',
                    'backend' => FileBackend::class,
                    'groups' => ['custom', 'pages']
                ]
            ],
            'partial_backend_and_groups' => [
                [
                    'backend' => 'CustomBackend',
                    'groups' => ['custom']
                ],
                [
                    'frontend' => VariableFrontend::class,
                    'backend' => 'CustomBackend',
                    'groups' => ['custom']
                ]
            ],
            'complete_custom_configuration' => [
                [
                    'frontend' => 'CustomFrontend',
                    'backend' => 'CustomBackend',
                    'groups' => ['custom', 'pages']
                ],
                [
                    'frontend' => 'CustomFrontend',
                    'backend' => 'CustomBackend',
                    'groups' => ['custom', 'pages']
                ]
            ],
            'additional_custom_options' => [
                [
                    'frontend' => 'CustomFrontend',
                    'options' => ['compression' => true],
                    'customKey' => 'customValue'
                ],
                [
                    'frontend' => 'CustomFrontend',
                    'backend' => FileBackend::class,
                    'groups' => ['system'],
                    'options' => ['compression' => true],
                    'customKey' => 'customValue'
                ]
            ],
            'override_all_with_additional_options' => [
                [
                    'frontend' => 'CustomFrontend',
                    'backend' => 'CustomBackend',
                    'groups' => ['custom'],
                    'options' => ['maxFileSize' => 1024],
                    'tags' => ['tag1', 'tag2']
                ],
                [
                    'frontend' => 'CustomFrontend',
                    'backend' => 'CustomBackend',
                    'groups' => ['custom'],
                    'options' => ['maxFileSize' => 1024],
                    'tags' => ['tag1', 'tag2']
                ]
            ],
            'empty_groups_array' => [
                [
                    'groups' => []
                ],
                [
                    'frontend' => VariableFrontend::class,
                    'backend' => FileBackend::class,
                    'groups' => []
                ]
            ],
            'null_values_in_existing_config' => [
                [
                    'frontend' => null,
                    'backend' => 'CustomBackend',
                    'groups' => null
                ],
                [
                    'frontend' => null,
                    'backend' => 'CustomBackend',
                    'groups' => null
                ]
            ],
            'false_values_in_existing_config' => [
                [
                    'frontend' => false,
                    'backend' => FileBackend::class,
                    'groups' => false
                ],
                [
                    'frontend' => false,
                    'backend' => FileBackend::class,
                    'groups' => false
                ]
            ]
        ];
    }

    /**
     * @dataProvider cacheConfigurationDataProvider
     */
    public function testSetupCacheConfiguration($existingConfig, array $expectedConfig): void
    {
        if ($existingConfig === null) {
            $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations'] = [];
        } else {
            $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations'][ExtensionSetup::CACHE_KEY] = $existingConfig;
        }

        ExtensionSetup::setup();

        $this->assertEquals(
            $expectedConfig,
            $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations'][ExtensionSetup::CACHE_KEY]
        );
    }

    public function testSetupDoesNotAffectOtherCacheConfigurations(): void
    {
        $otherCacheConfig = [
            'frontend' => 'OtherFrontend',
            'backend' => 'OtherBackend',
            'groups' => ['other']
        ];

        $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['other_cache'] = $otherCacheConfig;

        ExtensionSetup::setup();

        $this->assertEquals(
            $otherCacheConfig,
            $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['other_cache']
        );
    }

    public function testSetupWithComplexGlobalStructure(): void
    {
        $existingGlobalConfig = [
            'SYS' => [
                'caching' => [
                    'cacheConfigurations' => [
                        'other_cache' => [
                            'frontend' => 'OtherFrontend',
                            'backend' => 'OtherBackend'
                        ]
                    ]
                ],
                'other_config' => 'value'
            ]
        ];

        $GLOBALS['TYPO3_CONF_VARS'] = $existingGlobalConfig;

        ExtensionSetup::setup();

        $this->assertEquals(
            [
                'frontend' => VariableFrontend::class,
                'backend' => FileBackend::class,
                'groups' => ['system']
            ],
            $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations'][ExtensionSetup::CACHE_KEY]
        );

        $this->assertEquals(
            'value',
            $GLOBALS['TYPO3_CONF_VARS']['SYS']['other_config']
        );

        $this->assertEquals(
            [
                'frontend' => 'OtherFrontend',
                'backend' => 'OtherBackend'
            ],
            $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['other_cache']
        );
    }

    public function testSetupMultipleCalls(): void
    {
        $customConfig = [
            'frontend' => 'CustomFrontend',
            'backend' => 'CustomBackend',
            'groups' => ['custom']
        ];

        $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations'][ExtensionSetup::CACHE_KEY] = $customConfig;

        ExtensionSetup::setup();
        ExtensionSetup::setup();
        ExtensionSetup::setup();

        $this->assertEquals(
            $customConfig,
            $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations'][ExtensionSetup::CACHE_KEY]
        );
    }

    public function testCacheKeyConstant(): void
    {
        $this->assertEquals('cps_shortnr', ExtensionSetup::CACHE_KEY);
        $this->assertEquals(ExtensionSetup::EXT_KEY, ExtensionSetup::CACHE_KEY);
    }
}
