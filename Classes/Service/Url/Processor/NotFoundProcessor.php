<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Processor;

use CPSIT\ShortNr\Config\DTO\ConfigItemInterface;
use CPSIT\ShortNr\Exception\ShortNrNotFoundException;
use CPSIT\ShortNr\Exception\ShortNrProcessorException;
use CPSIT\ShortNr\Exception\ShortNrSiteFinderException;
use CPSIT\ShortNr\Service\DataProvider\DTO\PageData;
use CPSIT\ShortNr\Service\Url\Condition\DTO\ConfigMatchCandidate;
use CPSIT\ShortNr\Service\Url\Processor\DTO\ProcessorDecodeResult;
use CPSIT\ShortNr\Service\Url\Processor\DTO\ProcessorDecodeResultInterface;
use CPSIT\ShortNr\Service\Url\ValidateUriTrait;
use Symfony\Component\Filesystem\Path;

/**
 * We share the same DI constructor as the PageProcessor
 */
class NotFoundProcessor extends PageProcessor
{
    use ValidateUriTrait;

    public const NOT_FOULD_PROCESSOR_TYPE = '__notFound__';

    /**
     * @return string
     */
    public function getType(): string
    {
        // internal type, don't use in config
        return self::NOT_FOULD_PROCESSOR_TYPE;
    }

    /**
     * @param ConfigMatchCandidate $candidate
     * @param ConfigItemInterface $config
     * @return ProcessorDecodeResultInterface
     * @throws ShortNrSiteFinderException
     * @throws ShortNrNotFoundException
     * @throws ShortNrProcessorException
     */
    public function decode(ConfigMatchCandidate $candidate, ConfigItemInterface $config): ProcessorDecodeResultInterface
    {
        $notFound = $config->getNotFound();
        // empty or missing config deactivate the notFound Logic and return an NULL processorResult. That will continue the Middleware typo3 stack
        if (empty($notFound)) {
            // NotFound logic is disabled
            return new ProcessorDecodeResult(null);
        }

        // full uri / domain as notFound Handling found use that instead
        if ($this->validateUri($notFound)) {
            return new ProcessorDecodeResult($notFound);
        }

        // numeric not found config found treat it as PageUid and resolve it
        if (is_numeric($notFound)) {
            $pageData = $this->pageDataProvider->getPageData([
                'uid' => (int)$notFound
            ], $config);
            if ($pageData instanceof PageData) {
                // Load Site and language base path
                $basePath = $this->siteResolver->getSiteBaseUri($pageData->getUid(), $pageData->getLanguageId());

                // Concat the path segments to a complete path
                return new ProcessorDecodeResult(Path::join($basePath, $pageData->getSlug()));
            }
        }

        // If notFound is set but invalid, throw an exception
        throw new ShortNrProcessorException("Invalid notFound configuration: $notFound");
    }
}
