<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\EncodeNormalizer;

use CPSIT\ShortNr\Cache\CacheManager;
use CPSIT\ShortNr\Config\ConfigLoader;
use CPSIT\ShortNr\Config\DTO\ConfigItemInterface;
use CPSIT\ShortNr\Event\ShortNrEncodingConfigItemEvent;
use CPSIT\ShortNr\Exception\ShortNrCacheException;
use CPSIT\ShortNr\Exception\ShortNrConfigException;
use CPSIT\ShortNr\Service\Url\Demand\Encode\ConfigNameEncoderDemand;
use CPSIT\ShortNr\Service\Url\Demand\Encode\EncoderDemandInterface;
use CPSIT\ShortNr\Service\Url\Demand\Encode\EnvironmentEncoderDemand;
use CPSIT\ShortNr\Service\Url\Demand\Encode\ObjectEncoderDemand;
use CPSIT\ShortNr\Traits\PluginSignatureTrait;
use Psr\EventDispatcher\EventDispatcherInterface;
use Throwable;
use TYPO3\CMS\Core\Domain\Page;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Extbase\Mvc\ExtbaseRequestParameters;
use TYPO3\CMS\Extbase\Persistence\Generic\Mapper\DataMap;
use TYPO3\CMS\Extbase\Persistence\Generic\Mapper\DataMapFactory;
use TYPO3\CMS\Frontend\Page\PageInformation;

class EncodeConfigNormalizerService
{
    use PluginSignatureTrait;

    public function __construct(
        private readonly ConfigLoader $configLoader,
        private readonly DataMapFactory $dataMapFactory,
        private readonly CacheManager $cacheManager,
        private readonly EventDispatcherInterface $eventDispatcher
    )
    {}

    /**
     * @param EncoderDemandInterface $demand
     * @return ConfigItemInterface[]
     * @throws ShortNrCacheException
     * @throws ShortNrConfigException
     */
    public function getConfigItemForDemand(EncoderDemandInterface $demand): array
    {
        $configItems = match (true) {
            $demand instanceof ConfigNameEncoderDemand => [$this->configLoader->getConfig()->getConfigItem($demand->getConfigName())],
            $demand instanceof ObjectEncoderDemand => $this->resolveEntityToConfigItem($demand->getObject()),
            $demand instanceof EnvironmentEncoderDemand => $this->resolveConfigItemFromEnvironmentDemand($demand),
            default => []
        };

        /** @var ShortNrEncodingConfigItemEvent $event */
        $event = $this->eventDispatcher->dispatch(new ShortNrEncodingConfigItemEvent($configItems));
        return $event->getConfigItems();
    }

    /**
     * @param object $entity
     * @return ConfigItemInterface[]
     * @throws ShortNrCacheException
     * @throws ShortNrConfigException
     */
    private function resolveEntityToConfigItem(object $entity): array
    {
        if ($entity instanceof Page || $entity instanceof PageInformation) {
            // default pages return
            return [$this->configLoader->getConfig()->getConfigItem('pages')];
        }

        try {
            return $this->configLoader->getConfig()->getConfigItemsByTableName(
                $this->getTableNameFromObject($entity)
            );
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @param EnvironmentEncoderDemand $demand
     * @return ConfigItemInterface[]
     * @throws ShortNrCacheException
     * @throws ShortNrConfigException
     */
    private function resolveConfigItemFromEnvironmentDemand(EnvironmentEncoderDemand $demand): array
    {
        $configs = [];
        if ($demand->getExtbaseRequestParameters()) {
            array_push($configs, ...$this->resolveExtbaseParameters($demand->getExtbaseRequestParameters()));
        } elseif ($demand->getPageRoutingArguments()) {
            array_push($configs, ...$this->resolvePageRouteArguments($demand->getPageRoutingArguments()));
        }

        // add pages at the end as fallback
        $configs[] = $this->configLoader->getConfig()->getConfigItem('pages');
        if (!empty($configs)) {
            return $configs;
        }

        return [];
    }

    /**
     * @param ExtbaseRequestParameters $extbaseRequestParameters
     * @return ConfigItemInterface[]
     * @throws ShortNrCacheException
     * @throws ShortNrConfigException
     */
    private function resolveExtbaseParameters(ExtbaseRequestParameters $extbaseRequestParameters): array
    {
        $configs = [];
        foreach ($this->configLoader->getConfig()->getConfigItems() as $configItem) {
            if (!empty($configItem->getPluginConfig())) {
                $pluginConfig = $configItem->getPluginConfig();
                if (
                    $extbaseRequestParameters->getControllerExtensionName() === $pluginConfig['extension']??null &&
                    $extbaseRequestParameters->getControllerActionName() === $pluginConfig['action']??null &&
                    $extbaseRequestParameters->getControllerName() === $pluginConfig['controller']??null
                ) {

                    if (!$this->newsPluginWorkaround($extbaseRequestParameters->getPluginName(), $pluginConfig['plugin'], $extbaseRequestParameters->getControllerExtensionName())) {
                        continue;
                    }


                    // check if the argument name exists
                    try {
                        if (empty($extbaseRequestParameters->getArgument($pluginConfig['objectName']??''))) {
                            continue;
                        }
                    } catch (Throwable) {
                        continue;
                    }

                    $configs[$configItem->getName()] = $configItem;
                }
            }
        }

        return array_values($configs);
    }

    /**
     * @param string $incomingPluginName
     * @param string $configPluginName
     * @param string $extension
     * @return bool
     */
    private function newsPluginWorkaround(string $incomingPluginName, string $configPluginName, string $extension): bool
    {
        // news way
        if ( strtolower($extension) === 'news') {
            // news somehow force Pi1 for the plugin name...
            return strcasecmp($incomingPluginName, $configPluginName)  === 0 || strtolower($configPluginName) === 'pi1';
        }

        // correct way
        return strcasecmp($incomingPluginName, $configPluginName)  === 0;
    }


    /**
     * @param PageArguments $pageArguments
     * @return ConfigItemInterface[]
     * @throws ShortNrCacheException
     * @throws ShortNrConfigException
     */
    private function resolvePageRouteArguments(PageArguments $pageArguments): array
    {
        $configItemsByPluginSignature = [];
        foreach ($this->configLoader->getConfig()->getConfigItems() as $configItem) {
            if (!empty($configItem->getPluginConfig())) {
                $pluginConfig = $configItem->getPluginConfig();
                $signature = $this->generatePluginSignature($pluginConfig['extension'] ?? '', $pluginConfig['plugin'] ?? '');
                $configItemsByPluginSignature[$signature] = $configItem;
            }
        }

        $configs = [];
        foreach ([$pageArguments->getArguments(),
            $pageArguments->getStaticArguments(),
            $pageArguments->getDynamicArguments(),
            $pageArguments->getRouteArguments(),
            $pageArguments->getQueryArguments() ] as $arguments) {

            if (!empty($arguments)) {
                foreach ($arguments as $signature => $data) {
                    if (isset($configItemsByPluginSignature[$signature])) {
                        $configItem = $configItemsByPluginSignature[$signature];
                        $pluginConfig = $configItem->getPluginConfig();

                        $pluginAction = $pluginConfig['action'] ?? null;
                        $pluginController = $pluginConfig['controller'] ?? null;
                        $pluginObjectName = $pluginConfig['objectName'] ?? null;

                        $argAction = $data['action'] ?? null;
                        $argController = $data['controller'] ?? null;

                        if (
                            ($argAction && $argAction === $pluginAction) &&
                            ($argController && $argController === $pluginController) &&
                            ($pluginObjectName && isset($data[$pluginObjectName]))
                        ) {
                            $configs[$configItem->getName()] = $configItem;
                        }
                    }
                }
            }
        }

        return array_values($configs);
    }

    /**
     * @param object $object
     * @return string
     * @throws ShortNrCacheException
     */
    private function getTableNameFromObject(object $object): string
    {
        /** @var DataMap $factoryResult */
        return $this->cacheManager->getType3CacheValue(
            'db_dataMapper_' . $object::class,
            fn() => $this->dataMapFactory->buildDataMap($object::class)?->getTableName(),
            tags: ['all', 'meta', 'database', 'table']
        );
    }
}
