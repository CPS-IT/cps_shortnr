<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Processor;

use CPSIT\ShortNr\Config\DTO\ConfigItemInterface;
use CPSIT\ShortNr\Config\Enums\ConfigEnum;
use CPSIT\ShortNr\Domain\Repository\ShortNrRepository;
use CPSIT\ShortNr\Exception\ShortNrNotFoundException;
use CPSIT\ShortNr\Exception\ShortNrProcessorException;
use CPSIT\ShortNr\Service\PlatformAdapter\Typo3\SiteResolverInterface;
use CPSIT\ShortNr\Service\Url\Demand\Encode\EncoderDemandInterface;
use CPSIT\ShortNr\Traits\PluginSignatureTrait;
use Throwable;
use TypedPatternEngine\Compiler\MatchResult;

class PluginProcessor extends AbstractProcessor implements ProcessorInterface
{
    use PluginSignatureTrait;

    public function __construct(
        private readonly ShortNrRepository $repository,
        private readonly SiteResolverInterface $siteResolver
    )
    {}

    /**
     * @return string
     */
    public function getType(): string
    {
        return 'plugin';
    }

    /**
     * @param ConfigItemInterface $configItem
     * @param MatchResult $matchResult
     * @return string|null
     * @throws ShortNrNotFoundException
     * @throws ShortNrProcessorException
     */
    public function decode(ConfigItemInterface $configItem, MatchResult $matchResult): ?string
    {
        try {
            return $this->getPluginUrl($configItem, $matchResult);
        }catch (ShortNrProcessorException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new ShortNrNotFoundException(previous: $e);
        }
    }

    public function encode(ConfigItemInterface $configItem, EncoderDemandInterface $demand): ?string
    {
        $requiredFields = $this->getRequiredEncodingFields($configItem);

        return null;
    }



    /**
     * @param ConfigItemInterface $configItem
     * @param MatchResult $matchResult
     * @return string
     * @throws ShortNrProcessorException
     * @throws ShortNrNotFoundException
     */
    private function getPluginUrl(ConfigItemInterface $configItem, MatchResult $matchResult): string
    {
        $conditions = $matchResult->toArray();
        unset($conditions['input']);
        $uidKey = $configItem->getRecordIdentifier();
        $languageKey = $configItem->getLanguageField();
        try {
            // we need to fetch it since we must include potential other conditions from the configItem
            $rows = $this->repository->resolveTable([$uidKey, $languageKey], $configItem->getTableName(), $conditions + $configItem->getCondition());
        } catch (Throwable) {
            throw new ShortNrNotFoundException();
        }

        $pluginConfig = $configItem->getPluginConfig() ?? throw new ShortNrProcessorException('Missing Plugin Processor Config '. ConfigEnum::Plugin->value . ' in config: ' . $configItem->getName());
        $basePid = $pluginConfig['pid'] ?? throw new ShortNrProcessorException('Missing Plugin Processor Config \'pid\'');
        $plugin = $pluginConfig['plugin'] ?? throw new ShortNrProcessorException('Missing Plugin Processor Config \'plugin\'');
        $extension = $pluginConfig['extension'] ?? throw new ShortNrProcessorException('Missing Plugin Processor Config \'extension\'');
        $action = $pluginConfig['action'] ?? throw new ShortNrProcessorException('Missing Plugin Processor Config \'action\'');
        $controller = $pluginConfig['controller'] ?? throw new ShortNrProcessorException('Missing Plugin Processor Config \'controller\'');
        $objectName = $pluginConfig['objectName'] ?? 'uid';
        $pluginSignature = $this->generatePluginSignature($extension, $plugin);

        foreach ($rows as $row) {
            $uid = $row[$uidKey];
            $languageUid = $row[$languageKey];

            $pluginConfig = [
                'id' => $basePid,
                $pluginSignature => [
                    'action' => $action,
                    'controller' => $controller,
                    $objectName => $uid
                ]
            ];
            try {
                return $this->siteResolver->getUriByPageId(
                    $basePid,
                    $languageUid,
                    $pluginConfig
                );
            } catch (Throwable) {}
        }

        throw new ShortNrNotFoundException();
    }
}
