<?php
namespace CPSIT\CpsShortnr\Tests\Functional\Controller;

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

require_once __DIR__ . '/../AbstractShortnrTestCase.php';

use CPSIT\CpsShortnr\Controller\PageNotFoundController;
use CPSIT\CpsShortnr\Tests\Functional\AbstractShortnrTestCase;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

class PageNotFoundControllerTest extends AbstractShortnrTestCase
{
    /**
     * @return array
     */
    public function resolvePathRedirectsToExpectedPathDataProvider()
    {
        return [
            'Page' => [
                'P6',
                'index.php?id=6',
            ],
            'Page with language uid 1' => [
                'P1-1',
                'index.php?id=1&L=1',
            ],
            'News' => [
                'N456',
                'index.php?id=1&tx_news_pi1%5Bcontroller%5D=News&tx_news_pi1%5Baction%5D=detail&tx_news_pi1%5Bnews%5D=456',
            ],
        ];
    }

    /**
     * @test
     * @dataProvider resolvePathRedirectsToExpectedPathDataProvider
     * @param string $currentUrl
     * @param string $expectedPath
     */
    public function resolvePathRedirectsToExpectedPath($currentUrl, $expectedPath)
    {
        $subject = $this->getMock(PageNotFoundController::class, ['shutdown']);
        $subject->expects($this->once())->method('shutdown')->with($expectedPath);

        $subject->resolvePath([
            'currentUrl' => $currentUrl,
        ]);
    }

    /**
     * @test
     */
    public function invalidPathCallsPageNotFoundHandler()
    {
        $frontendController = $this->getMock(
            TypoScriptFrontendController::class,
            ['pageNotFoundHandler'],
            [
                $GLOBALS['TYPO3_CONF_VARS'],
                1,
                0,
            ]
        );
        $frontendController->expects($this->once())->method('pageNotFoundHandler')->with('', '', 'No record for "42" found');
        $GLOBALS['TSFE'] = $frontendController;

        $subject = new PageNotFoundController();
        $subject->resolvePath([
            'currentUrl' => 'P42',
        ]);
    }
}
