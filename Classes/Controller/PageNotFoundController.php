<?php
namespace CPSIT\CpsShortnr\Controller;

/***************************************************************
 *  Copyright notice
 *  (c) 2012 Nicole Cordes <cordes@cps-it.de>
 *  All rights reserved
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use CPSIT\CpsShortnr\Shortlink\Decoder;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\TypoScript\TemplateService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use TYPO3\CMS\Frontend\Page\PageRepository;

/**
 * Evaluates if the given url is a short link and redirects to parent page
 *
 * @author Nicole Cordes <cordes@cps-it.de>
 */
class PageNotFoundController implements SingletonInterface
{
    /**
     * @var array
     */
    private $configuration = [];

    /**
     * @var TypoScriptFrontendController
     */
    private $tempTSFE = null;

    /**
     * @param array $configuration
     */
    public function __construct(array $configuration = null)
    {
        $this->configuration = ($configuration !== null) ? $configuration
            : unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['cps_shortnr']);
    }

    /**
     * @param array $params
     * @return void
     */
    public function resolvePath($params)
    {
        // If no config file was defined return to original pageNotFound_handling
        if (substr($this->configuration['configFile'], 0, 5) !== 'FILE:') {
            $configurationFile = PATH_site . $this->configuration['configFile'];
        } else {
            $configurationFile = GeneralUtility::getFileAbsFileName(substr($this->configuration['configFile'], 5));
        }

        try {
            $shortlinkDecoder = Decoder::createFromConfigurationFile($configurationFile, $params['currentUrl'], $this->configuration['regExp']);
        } catch (\RuntimeException $exception) {
            $this->executePageNotFoundHandling($exception->getMessage());
        }

        // Write register
        array_push($GLOBALS['TSFE']->registerStack, $GLOBALS['TSFE']->register);
        $this->initTSFE();
        $shortlinkParts = $shortlinkDecoder->getShortlinkParts();
        foreach ($shortlinkParts as $key => $value) {
            $GLOBALS['TSFE']->register['tx_cpsshortnr_match_' . $key] = $value;
        }

        try {
            $recordInformation = $shortlinkDecoder->getRecordInformation();
        } catch (\RuntimeException $exception) {
            $this->executePageNotFoundHandling($exception->getMessage());
        }

        // Check if record is in current rootline
        $tsfe = $this->getTypoScriptFrontendController();
        $tsfe->id = $recordInformation['table'] === 'pages' ? $recordInformation['record']['uid']
            : $recordInformation['record']['pid'];
        $tsfe->domainStartPage = $tsfe->findDomainRecord($tsfe->TYPO3_CONF_VARS['SYS']['recursiveDomainSearch']);
        $tsfe->getPageAndRootlineWithDomain($GLOBALS['TSFE']->domainStartPage);
        if (!empty($tsfe->pageNotFound)) {
            $this->executePageNotFoundHandling('ID was outside the domain');
        }

        $this->shutdown($shortlinkDecoder->getPath());
    }

    /**
     * @param string $reason
     * @return void
     */
    protected function executePageNotFoundHandling($reason)
    {
        $GLOBALS['TSFE']->pageNotFoundHandler($this->configuration['pageNotFound_handling'], '', $reason);
        exit;
    }

    /**
     * @return TypoScriptFrontendController
     */
    protected function getTypoScriptFrontendController()
    {
        return $GLOBALS['TSFE'];
    }

    /**
     * @return void
     */
    protected function initTSFE()
    {
        $this->tempTSFE = $GLOBALS['TSFE'];

        // Only open urls for the current domain
        $GLOBALS['TSFE']->config['mainScript'] = 'index.php';
        $GLOBALS['TSFE']->config['config']['typolinkEnableLinksAcrossDomains'] = 0;

        // Enable realurl
        $GLOBALS['TSFE']->config['config']['tx_realurl_enable'] = 1;
        $GLOBALS['TSFE']->config['config']['tx_hellurl_enable'] = 1;

        // Initialize the page select object
        $GLOBALS['TSFE']->sys_page = GeneralUtility::makeInstance(PageRepository::class);
        $GLOBALS['TSFE']->sys_page->versioningPreview = false;
        $GLOBALS['TSFE']->sys_page->versioningWorkspaceId = false;
        $GLOBALS['TSFE']->sys_page->init(false);

        // Initialize the template object
        $GLOBALS['TSFE']->tmpl = GeneralUtility::makeInstance(TemplateService::class);
        $GLOBALS['TSFE']->tmpl->init();
        $GLOBALS['TSFE']->tmpl->tt_track = 0;
    }

    /**
     * @param string $path
     */
    protected function shutdown($path)
    {
        // Restore TSFE
        $GLOBALS['TSFE'] = $this->tempTSFE;

        // Check for redirection
        if (!empty($path)) {
            $GLOBALS['TSFE']->hook_eofe();
            header('HTTP/1.0 301 TYPO3 cps_shortnr redirect');
            header('Location: ' . GeneralUtility::locationHeaderUrl($path));
            exit;
        }
        $this->executePageNotFoundHandling('Empty path given');
    }
}
