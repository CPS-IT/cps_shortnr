<?php
if (!defined('TYPO3_MODE')) {
	die ('Access denied.');
}

	// Store old pageNotFound handling
$pageNotFound_handling = 'USER_FUNCTION:EXT:cps_shortnr/Classes/Controller/PageNotFoundController.php:tx_cpsshortnr_pagenotfoundcontroller->resolvePath';
if (!isset($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['cps_shortnr'])) {
		// Store current handler in extension configuration
	$extConfiguration = array(
		'pageNotFound_handling' => $GLOBALS['TYPO3_CONF_VARS']['FE']['pageNotFound_handling']
	);
	$GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['cps_shortnr'] = serialize($extConfiguration);
}

	// Set own pageNotFound handling
$GLOBALS['TYPO3_CONF_VARS']['FE']['pageNotFound_handling'] = $pageNotFound_handling;

?>