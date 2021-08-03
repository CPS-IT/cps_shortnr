<?php

declare(strict_types=1);

namespace CPSIT\CpsShortnr\Middleware;

use CPSIT\CpsShortnr\Service\Decoder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use TYPO3\CMS\Core\Http\RedirectResponse;

class ShortUrlMiddleware implements MiddlewareInterface
{
    /**
     * @var array
     */
    private array $configuration = [];

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $site = $request->getAttribute('site');
        $id = $request->getQueryParams()['id'] ?? $request->getParsedBody()['id'] ?? $site->getRootPageId();
        $type = $request->getQueryParams()['type'] ?? $request->getParsedBody()['type'] ?? '0';
        $url = $request->getUri()->getPath();
        $this->configuration = $this->getExtConfig();
        if (!str_starts_with($this->configuration['configFile'], 'FILE:')) {
            $configurationFile = Environment::getPublicPath() . '/' . $this->configuration['configFile'];
        } else {
            $configurationFile = GeneralUtility::getFileAbsFileName(substr($this->configuration['configFile'], 5));
        }

        $language = $request->getAttribute('language');
        $shortlinkDecoder = Decoder::createFromConfigurationFile($configurationFile, $url, $this->configuration['regExp']);

        $GLOBALS['TSFE'] = $this->typoScriptFrontendController = GeneralUtility::makeInstance(
            TypoScriptFrontendController::class,
            GeneralUtility::makeInstance(Context::class),
            $site,
            $language,
            $request->getAttribute('routing', new PageArguments((int)$id, (string)$type, []))
        );
        // Write register
        array_push($GLOBALS['TSFE']->registerStack, $GLOBALS['TSFE']->register);
        $shortlinkParts = $shortlinkDecoder->getShortlinkParts();


        if ($shortlinkParts) {
            foreach ($shortlinkParts as $key => $value) {
                $GLOBALS['TSFE']->register['tx_cpsshortnr_match_' . $key] = $value;
            }
            try {
                $recordInformation = $shortlinkDecoder->getRecordInformation();
            } catch (\RuntimeException $exception) {
            }

            if(empty($recordInformation) || $recordInformation['record']['hidden'] === 1 || $recordInformation['record']['deleted'] === 1 ) {
                return $this->redirectToPage((int) $this->configuration['pageNotFound_handling'],[],301);
            }


            $GLOBALS['TSFE']->id = $recordInformation['table'] === 'pages' ? $recordInformation['record']['uid']
                : $recordInformation['record']['pid'];
            $context  =  GeneralUtility::makeInstance(Context::class);
            $GLOBALS['TSFE']->sys_page = GeneralUtility::makeInstance(PageRepository::class, $context);
            $path = $shortlinkDecoder->getPath();
            return new RedirectResponse($path, 301);
        }
        return $handler->handle($request);
    }


    /**
     * @return mixed
     */
    protected function getExtConfig()
    {
        return GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('cps_shortnr');
    }

    protected function redirectToPage($uid, $data = [], $status = 301): RedirectResponse
    {
        $site = GeneralUtility::makeInstance(SiteFinder::class)->getSiteByPageId($uid);
        if ($this->piVars['languageCode']) {
            /** @var SiteLanguage $language */
            foreach ($site->getLanguages() as $language) {
                if ($language->getTwoLetterIsoCode() === $this->piVars['languageCode']) {
                    $data['_language'] = $language->getLanguageId();
                    break;
                }
            }
        }
        $url = $site->getRouter()->generateUri($uid, $data);
        return new RedirectResponse($url, $status);
    }
}
