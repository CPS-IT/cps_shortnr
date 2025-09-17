<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Processor;

use CPSIT\ShortNr\Config\DTO\ConfigItemInterface;
use CPSIT\ShortNr\Exception\ShortNrNotFoundException;
use CPSIT\ShortNr\Exception\ShortNrProcessorException;
use CPSIT\ShortNr\Traits\ValidateUriTrait;
use TypedPatternEngine\Compiler\MatchResult;

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
     * @param ConfigItemInterface $configItem
     * @param MatchResult $matchResult
     * @return string|null
     * @throws ShortNrNotFoundException
     * @throws ShortNrProcessorException
     */
    public function decode(ConfigItemInterface $configItem, MatchResult $matchResult): ?string
    {
        $notFound = $configItem->getNotFound();
        $uidField = $configItem->getRecordIdentifier();
        // empty or missing config deactivate the notFound Logic and return an NULL processorResult. That will continue the Middleware typo3 stack
        if (empty($notFound) || empty($uidField)) {
            // NotFound logic is disabled
            return null;
        }

        // numeric not found config found treat it as PageUid and resolve it
        if (is_numeric($notFound)) {
            return $this->pageDataProvider->getPageData([$uidField =>  $notFound], $configItem);
        } elseif ($this->validateUri($notFound)) {
            // full uri / domain as notFound Handling found use that instead
            return $notFound;
        }

        // If notFound is set but invalid, throw an exception
        throw new ShortNrProcessorException("Invalid notFound configuration: $notFound");
    }
}
