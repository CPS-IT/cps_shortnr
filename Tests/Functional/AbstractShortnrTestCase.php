<?php
namespace CPSIT\CpsShortnr\Tests\Functional;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2017 Nicole Cordes <cordes@cps-it.de>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

abstract class AbstractShortnrTestCase extends FunctionalTestCase
{
    /**
     * @var array
     */
    protected $configurationToUseInTestInstance = [
        'EXT' => [
            'extConf' => [
                'cps_shortnr' => 'a:3:{s:21:"pageNotFound_handling";s:0:"";s:10:"configFile";s:46:"FILE:EXT:cps_shortnr/Resources/cps_shortnr.txt";s:6:"regExp";s:25:"([a-zA-Z]+)(\\d+)(-(\\d+))?";}',
            ],
        ],
    ];

    /**
     * @var array
     */
    protected $testExtensionsToLoad = [
        'typo3conf/ext/cps_shortnr',
        'typo3conf/ext/news',
    ];

    protected function setUp()
    {
        parent::setUp();

        $this->importDataSet('ntf://Database/pages.xml');
        $this->importDataSet('ntf://Database/pages_language_overlay.xml');
        $this->importDataSet('ntf://Database/sys_language.xml');

        $fixturePath = ORIGINAL_ROOT . 'typo3conf/ext/cps_shortnr/Tests/Functional/Fixtures/';
        $this->importDataSet($fixturePath . 'tx_news_domain_model_news.xml');

        $frontendController = new TypoScriptFrontendController($GLOBALS['TYPO3_CONF_VARS'], 1, 0);
        $GLOBALS['TSFE'] = $frontendController;
    }
}
