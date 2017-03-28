<?php
namespace CPSIT\CpsShortnr\Tests\Functional\Shortlink;

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

use CPSIT\CpsShortnr\Shortlink\Shortlink;
use CPSIT\CpsShortnr\Tests\Functional\AbstractShortnrTestCase;

class ShortlinkTest extends AbstractShortnrTestCase
{
    /**
     * @return array
     */
    public function createReturnsShortlinkForRecordDataProvider()
    {
        return [
            'Page' => [
                [
                    'record' => 6,
                    'table' => 'pages',
                ],
                'P6',
            ],
            'News' => [
                [
                    'record' => 456,
                    'table' => 'tx_news_domain_model_news',
                ],
                'N456',
            ],
            'News with language uid 2' => [
                [
                    'record' => 457,
                    'table' => 'tx_news_domain_model_news',
                ],
                'N456-2',
            ],
            'Message' => [
                [
                    'record' => 42,
                    'table' => 'tx_news_domain_model_news',
                ],
                'M42',
            ],
        ];
    }

    /**
     * @test
     * @dataProvider createReturnsShortlinkForRecordDataProvider
     * @param array $configuration
     * @param string $expectedShortlink
     */
    public function createReturnsShortlinkForRecord(array $configuration, $expectedShortlink)
    {
        $subject = new Shortlink();

        $this->assertEquals($expectedShortlink, $subject->create('', $configuration));
    }

    /**
     * @test
     */
    public function createReturnsShortlinkForPageWithLanguageUid()
    {
        $_GET['L'] = 2;

        $configuration = [
            'record' => 2,
            'table' => 'pages',
        ];

        $subject = new Shortlink();

        $this->assertEquals('P2-2', $subject->create('', $configuration));
    }
}
