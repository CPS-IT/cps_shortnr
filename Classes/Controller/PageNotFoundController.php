<?php
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

/**
 * Evaluates if the given url is a short link and redirects to parent page
 *
 * @author Nicole Cordes <cordes@cps-it.de>
 * @package TYPO3
 * @subpackage cps_shortnr
 */
class PageNotFoundController {

	/**
	 * @var array
	 */
	var $configuration = array();

	/**
	 * @var array
	 */
	var $params = array();

	/**
	 * @var tslib_fe|NULL
	 */
	var $tempTSFE = NULL;

	/**
	 * @var array
	 */
	var $typoScriptArray = array();

	/**
	 * @param array $params
	 * @param tslib_fe $pObj
	 * @return void
	 */
	public function resolvePath($params, $pObj) {
		$this->params = $params;
		$this->init();

		// If no config file was defined return to original pageNotFound_handling
		if (substr($this->configuration['configFile'], 0, 5) !== 'FILE:') {
			$configurationFile = PATH_site . $this->configuration['configFile'];
		} else {
			$configurationFile = \TYPO3\CMS\Core\Utility\GeneralUtility::getFileAbsFileName(substr($this->configuration['configFile'], 5));
		}
		if (!file_exists($configurationFile)) {
			$this->executePageNotFoundHandling();
		}

		// Convert file content to TypoScript array
		$this->getTypoScriptArray($configurationFile);
		if (!isset($this->typoScriptArray['cps_shortnr'])) {
			$this->executePageNotFoundHandling();
		}

		// Manipulate TSFE object
		$this->initTSFE();

		// Write register
		array_push($GLOBALS['TSFE']->registerStack, $GLOBALS['TSFE']->register);
		$this->writeRegisterMatches();

		// Parse url and try to resolve any redirect
		/** @var tslib_cObj $contentObject */
		$contentObject = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('tslib_cObj');

		$path = $contentObject->cObjGetSingle($this->typoScriptArray['cps_shortnr'], $this->typoScriptArray['cps_shortnr.']);

		$this->shutdown($path);
	}

	/**
	 * @param string $content
	 * @param array $configuration
	 * @return int
	 */
	public function checkPidInRootline($content, $configuration) {
		$content = (int)$content;
		$GLOBALS['TSFE']->id = $content;
		$GLOBALS['TSFE']->domainStartPage = $GLOBALS['TSFE']->findDomainRecord($GLOBALS['TSFE']->TYPO3_CONF_VARS['SYS']['recursiveDomainSearch']);
		$GLOBALS['TSFE']->getPageAndRootlineWithDomain($GLOBALS['TSFE']->domainStartPage);
		if (!empty($GLOBALS['TSFE']->pageNotFound)) {
			$this->init();
			$this->executePageNotFoundHandling('ID was outside the domain');
		}

		return $content;
	}

	/**
	 * @return void
	 */
	protected function init() {
		$this->configuration = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['cps_shortnr']);
	}

	/**
	 * @param string $reason
	 * @return void
	 */
	protected function executePageNotFoundHandling($reason = '') {
		$reason = $reason ?: $this->params['reasonText'];
		$GLOBALS['TSFE']->pageNotFoundHandler($this->configuration['pageNotFound_handling'], '', $reason);
		exit;
	}

	/**
	 * @param string $configurationFile
	 * @return void
	 */
	protected function getTypoScriptArray($configurationFile) {
		$file = \TYPO3\CMS\Core\Utility\GeneralUtility::getURL($configurationFile);
		if (empty($file)) {
			$this->executePageNotFoundHandling();
		} else {
			/** @var t3lib_TSparser $typoScriptParser */
			$typoScriptParser = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('t3lib_TSparser');
			$typoScriptParser->parse($file, '');

			$this->typoScriptArray = $typoScriptParser->setup;
		}
	}

	/**
	 * @return void
	 */
	protected function initTSFE() {
		$this->tempTSFE = $GLOBALS['TSFE'];

		// Only open urls for the current domain
		$GLOBALS['TSFE']->config['mainScript'] = 'index.php';
		$GLOBALS['TSFE']->config['config']['typolinkEnableLinksAcrossDomains'] = 0;

		// Enable realurl
		$GLOBALS['TSFE']->config['config']['tx_realurl_enable'] = 1;

		// Initialize the page select object
		$GLOBALS['TSFE']->sys_page = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('t3lib_pageSelect');
		$GLOBALS['TSFE']->sys_page->versioningPreview = FALSE;
		$GLOBALS['TSFE']->sys_page->versioningWorkspaceId = FALSE;
		$GLOBALS['TSFE']->sys_page->init(FALSE);

		// Initialize the template object
		$GLOBALS['TSFE']->tmpl = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('t3lib_TStemplate');
		$GLOBALS['TSFE']->tmpl->init();
		$GLOBALS['TSFE']->tmpl->tt_track = 0;

		$GLOBALS['TSFE']->getCompressedTCarray();
	}

	/**
	 * @return void
	 */
	protected function writeRegisterMatches() {
		$regularExpression = $this->configuration['regExp'];
		$regularExpression = str_replace('/', '\\/', $regularExpression);

		preg_match('/' . $regularExpression . '/', $this->params['currentUrl'], $matches);
		if (count($matches)) {
			foreach ($matches as $key => $value) {
				$GLOBALS['TSFE']->register['tx_cpsshortnr_match_' . $key] = $value;
			}
			unset($key, $value);
		}
	}

	/**
	 * @param string $path
	 */
	protected function shutdown($path) {
		// Restore TSFE
		$GLOBALS['TSFE'] = $this->tempTSFE;

		// Check for redirection
		if (!empty($path)) {
			$GLOBALS['TSFE']->hook_eofe();
			header('HTTP/1.0 301 TYPO3 cps_shortnr redirect');
			header('Location: ' . \TYPO3\CMS\Core\Utility\GeneralUtility::locationHeaderUrl($path));
			exit;
		} else {
			$this->executePageNotFoundHandling();
		}
	}
}

class tx_cpsshortnr_pagenotfoundcontroller extends PageNotFoundController {
}

if (defined('TYPO3_MODE') && $GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/cps_shortnr/Classes/Controller/PageNotFoundController.php']) {
	include_once($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/cps_shortnr/Classes/Controller/PageNotFoundController.php']);
}

?>