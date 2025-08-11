<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Processor;

use CPSIT\ShortNr\Exception\ShortNrNotFoundException;
use CPSIT\ShortNr\Exception\ShortNrProcessorException;
use CPSIT\ShortNr\Service\Url\Demand\DecoderDemandInterface;
use CPSIT\ShortNr\Service\Url\ValidateUriTrait;

/**
 * We share the same DI constructor as the PageProcessor
 */
class NotFoundProcessor extends PageProcessor
{
    use ValidateUriTrait;

    public const NOT_FOUND_PROCESSOR_TYPE = '__NOT_FOUND_PROCESSOR_TYPE__';

    /**
     * @return string
     */
    public function getType(): string
    {
        // internal type, don't use in config
        return static::NOT_FOUND_PROCESSOR_TYPE;
    }

    /**
     * @param DecoderDemandInterface $demand
     * @return string|null
     * @throws ShortNrProcessorException|ShortNrNotFoundException
     */
    public function decode(DecoderDemandInterface $demand): ?string
    {
        $configItem = $demand->getConfigItem();
        $notFound = $configItem?->getNotFound();
        // empty or missing config deactivate the notFound Logic and return an NULL processorResult. That will continue the Middleware typo3 stack
        if (empty($notFound) || empty($configItem->getRecordIdentifier())) {
            // NotFound logic is disabled
            return null;
        }

        // numeric not found config found treat it as PageUid and resolve it
        if (is_numeric($notFound)) {
            return $this->pageDataProvider->getPageData([$configItem->getRecordIdentifier() => (int)$notFound], $demand->getConfigItem());
        } elseif ($this->validateUri($notFound)) {
            // full uri / domain as notFound Handling found use that instead
            return $notFound;
        }

        // If notFound is set but invalid, throw an exception
        throw new ShortNrProcessorException("Invalid notFound configuration: $notFound");
    }
}
