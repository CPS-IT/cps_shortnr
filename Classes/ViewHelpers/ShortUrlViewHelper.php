<?php declare(strict_types=1);

namespace CPSIT\ShortNr\ViewHelpers;

use CPSIT\ShortNr\Config\Enums\ViewHelperEncodingType;
use CPSIT\ShortNr\Exception\ShortNrViewHelperException;
use CPSIT\ShortNr\Service\EncoderService;
use CPSIT\ShortNr\Service\PlatformAdapter\Typo3\SiteResolverInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Fluid\Core\Rendering\RenderingContext;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

final class ShortUrlViewHelper extends AbstractViewHelper
{
    public function __construct(
        private readonly EncoderService $encoderService,
        private readonly SiteResolverInterface $siteResolver,
    )
    {}

    protected $escapeOutput = false;

    public function initializeArguments(): void
    {
        $this->registerArgument(
            name:'name',
            type: 'string',
            description: 'Supported ShortNr TableName'
        );
        $this->registerArgument(
            name: 'uid',
            type: 'string',
            description: 'Supported ShortNr Uid'
        );
        $this->registerArgument(
            name: 'languageUid',
            type: 'int',
            description: 'language ID of the target the links destination, if not provided fallback to current environment language'
        );
        $this->registerArgument(
            name: 'absolute',
            type: 'bool',
            description: 'Generate absolute URL [absolute => ..., uri => ...] (added the absolute URL)',
            defaultValue: false
        );
        $this->registerArgument(
            name: 'output',
            type: 'string',
            description: 'return the uri data as variable',
            required: true
        );
    }

    /**
     * @return string
     * @throws ShortNrViewHelperException
     */
    public function render(): string
    {
        /* TODO:
         * 1. use direct object insertion // i think that will be the most complex since we must use operator logic to determine what config is used
         * 2. uid + config-name combination
         * 3. Environment lookup in the (request queryParams or Attributes, maybe use the current page ID as indicator or go the same route as direct Object)
         */

        // use the request to acquire the queryParams where the Plugin and record information are embedded
        // iterate thu all 3 types (to-do up) and then iterate through every config based on the Priority

        $request = $this->getRequest();
        $absoluteUrl = (bool)($this->arguments['absolute'] ?? false);
        $languageid = $this->getLanguageUid($request, $this->arguments['languageUid']);

        /*$uri = match ($this->getCallType()) {
            ViewHelperEncodingType::configName => $this->encoderService->encode(),
            ViewHelperEncodingType::object =>  $this->encoderService->encode(),
            default => $this->encoderService->encode()
        };*/

        $uri = 'WIP'; // replace with encoding

        return $this->parseChildren(['uri' => $uri]);
    }

    /**
     * @return ViewHelperEncodingType
     */
    private function getCallType(): ViewHelperEncodingType
    {
        if (!empty($this->arguments['object'])) {
            return ViewHelperEncodingType::object;
        }

        if (!empty($this->arguments['name']) && !empty($this->arguments['uid'])) {
            return ViewHelperEncodingType::configName;
        }

        return ViewHelperEncodingType::environment;
    }

    /**
     * @param ServerRequestInterface $request
     * @param int|string|null $requestedLanguageUid
     * @return int
     * @throws ShortNrViewHelperException
     */
    private function getLanguageUid(ServerRequestInterface $request, null|int|string $requestedLanguageUid): int
    {
        if ($requestedLanguageUid === null || $requestedLanguageUid === '') {
            if (($languageUid = ($request->getAttribute('language')?->getLanguageId() ?? null)) !== null) {
                return $languageUid;
            }
        } else {
            $languages = $this->siteResolver->getLanguagesByRequest($request);
            if (isset($languages[$requestedLanguageUid]) && $languages[$requestedLanguageUid] instanceof SiteLanguage) {
                return $languages[$requestedLanguageUid]->getLanguageId();
            }
        }

        throw new ShortNrViewHelperException('Could not determine current language ID from Request');
    }

    /**
     * @param array $data
     * @return string
     */
    private function parseChildren(array $data): string
    {
        // Provide the URI to the tag content
        $this->templateVariableContainer->add($this->arguments['output'], $data);

        // Render everything inside the VH-tags
        $content = $this->renderChildren();

        // Clean up the variable container
        $this->templateVariableContainer->remove($this->arguments['output']);
        return $content;
    }

    /**
     * @return ServerRequestInterface
     * @throws ShortNrViewHelperException
     */
    private function getRequest(): ServerRequestInterface
    {
        $request = null;
        if ($this->renderingContext->hasAttribute(ServerRequestInterface::class)) {
            $request = $this->renderingContext->getAttribute(ServerRequestInterface::class);
        } elseif ($this->renderingContext instanceof RenderingContext) {
            $request = $this->renderingContext->getRequest();
        }

        if ($request instanceof ServerRequestInterface) {
            return $request;
        }

        throw new ShortNrViewHelperException('Required Request not found.');
    }
}
