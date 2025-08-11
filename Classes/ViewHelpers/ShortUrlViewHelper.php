<?php declare(strict_types=1);

namespace CPSIT\ShortNr\ViewHelpers;

use CPSIT\ShortNr\Exception\ShortNrViewHelperException;
use CPSIT\ShortNr\Service\EncoderService;
use CPSIT\ShortNr\Service\PlatformAdapter\Typo3\SiteResolverInterface;
use CPSIT\ShortNr\Service\Url\Demand\ConfigNameEncoderDemand;
use CPSIT\ShortNr\Service\Url\Demand\EncoderDemandInterface;
use CPSIT\ShortNr\Service\Url\Demand\EnvironmentEncoderDemand;
use CPSIT\ShortNr\Service\Url\Demand\ObjectEncoderDemand;
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
            description: 'Supported ShortNr Config Item Name'
        );
        $this->registerArgument(
            name: 'uid',
            type: 'string',
            description: 'Supported ShortNr Uid'
        );
        $this->registerArgument(
            name: 'object',
            type: 'object',
            description: 'Object that is supported like a DomainRecord Entity'
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
        $demand = $this->getEncodingDemand($this->getRequest());
        if ($demand === null) {
            return $this->parseChildren(['uri' => null]);
        }

        return $this->parseChildren(['uri' => $this->encoderService->encode($demand)]);
    }

    /**
     * @param ServerRequestInterface $request
     * @return EncoderDemandInterface|null
     * @throws ShortNrViewHelperException
     */
    private function getEncodingDemand(ServerRequestInterface $request): ?EncoderDemandInterface
    {
        if (($this->arguments['name'] ?? false) && ($this->arguments['uid'] ?? false)) {
            $demand = new ConfigNameEncoderDemand(
                (string) $this->arguments['name'],
                (int) $this->arguments['uid']
            );
        } elseif (!empty($this->arguments['object']) && is_object($this->arguments['object'])) {
            $demand = new ObjectEncoderDemand($this->arguments['object']);
        } else {
            $demand = new EnvironmentEncoderDemand(
                $request->getQueryParams(),
                $request->getAttribute('frontend.controller')?->page ?? [],
                $request->getAttribute('routing'),
                $request->getAttribute('extbase')
            );
        }

        return $demand
            ->setRequest($request)
            ->setLanguageId($this->getLanguageUid($request, $this->arguments['languageUid']))
            ->setAbsolute((bool)($this->arguments['absolute'] ?? false));
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
