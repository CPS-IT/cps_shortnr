<?php
namespace CPSIT\CpsShortnr\Service;

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

use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

class Shortlink
{
    /**
     * @param string $content
     * @param array $configuration
     * @return string
     */
    public function create($content, array $configuration)
    {
        if (empty($configuration['record']) && empty($configuration['record.'])) {
            throw new \RuntimeException('No record defined', 1490653681);
        }

        if (empty($configuration['table'])) {
            throw new \RuntimeException('No table defined', 1490653712);
        }

        $extensionConfiguration = GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('cps_shortnr');

        if (!str_starts_with($extensionConfiguration['configFile'], 'FILE:')) {
            $configurationFile = Environment::getPublicPath() . '/' . $extensionConfiguration['configFile'];
        } else {
            $configurationFile = GeneralUtility::getFileAbsFileName(substr($extensionConfiguration['configFile'], 5));
        }
        $encoder = Encoder::createFromConfigurationFile($configurationFile);

        if (empty($configuration['record.'])) {
            $recordUid = (int)$configuration['record'];
        } else {
            $contentObjectRenderer = GeneralUtility::makeInstance(ContentObjectRenderer::class);
            $recordUid = (int)$contentObjectRenderer->stdWrap(
                isset($configuration['record']) ? $configuration['record'] : '',
                $configuration['record.']
            );
        }

        return $encoder->getShortlink($recordUid, $configuration['table']);
    }
}
