<?php
namespace CPSIT\CpsShortnr\Shortlink;

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

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\TypoScript\Parser\TypoScriptParser;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Configuration\TypoScript\ConditionMatching\ConditionMatcher;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

class Encoder
{
    /**
     * @var array
     */
    private $configuration;

    /**
     * @var array
     */
    private $encodeFormat;

    /**
     * @param array $configuration
     * @param array $encodeFormat
     */
    public function __construct(array $configuration, array $encodeFormat)
    {
        $this->configuration = $configuration;
        $this->encodeFormat = $encodeFormat;
    }

    /**
     * @param string $configurationFile
     * @throws \RuntimeException
     * @return Encoder
     */
    public static function createFromConfigurationFile($configurationFile)
    {
        if (!file_exists($configurationFile)) {
            throw new \RuntimeException('Configuration file not found', 1490688798);
        }

        $file = GeneralUtility::getUrl($configurationFile);
        if (empty($file)) {
            throw new \RuntimeException('Configuration file could not be read', 1490653728);
        }

        $typoScriptParser = GeneralUtility::makeInstance(TypoScriptParser::class);
        $conditionMatcher = GeneralUtility::makeInstance(ConditionMatcher::class);
        $typoScriptParser->parse($file, $conditionMatcher);

        $typoScriptArray = $typoScriptParser->setup;

        if (!isset($typoScriptArray['cps_shortnr.'])) {
            throw new \RuntimeException('No "cps_shortnr" configuration found', 1490653738);
        }

        if (!isset($typoScriptArray['cps_shortnr.']['encoder.'])) {
            throw new \RuntimeException('No "encoder" configuration found', 1490654089);
        }

        $configuration = [];
        foreach ($typoScriptArray['cps_shortnr.'] as $key => $value) {
            if (empty($value['source.']['table'])) {
                continue;
            }

            $key = trim($key, '.');

            $configuration[$key] = [
                'table' => $value['source.']['table'],
            ];

            if (!empty($value['source.']['encodeMatchFields.'])) {
                $configuration[$key]['encodeMatchFields'] = $value['source.']['encodeMatchFields.'];
            }
        }

        return new self($configuration, $typoScriptArray['cps_shortnr.']['encoder.']);
    }

    /**
     * @param int $recordUid
     * @param string $table
     * @return string
     */
    public function getShortlink($recordUid, $table)
    {
        $record = BackendUtility::getRecordWSOL($table, $recordUid);
        if ($record === null) {
            return '';
        }

        $identifier = $this->findIdentifier($table, $record);
        if ($identifier === '') {
            return '';
        }

        $language = GeneralUtility::_GP('L');
        if (!empty($GLOBALS['TCA'][$table]['ctrl']['languageField'])) {
            $language = $record[$GLOBALS['TCA'][$table]['ctrl']['languageField']];
        }

        $languageParent = $record['uid'];
        if (!empty($GLOBALS['TCA'][$table]['ctrl']['transOrigPointerField'])
            && !empty($record[$GLOBALS['TCA'][$table]['ctrl']['transOrigPointerField']])
        ) {
            $languageParent = $record[$GLOBALS['TCA'][$table]['ctrl']['transOrigPointerField']];
        }

        $record['tx_cpsshortnr_identifier_lower'] = strtolower($identifier);
        $record['tx_cpsshortnr_identifier_upper'] = strtoupper($identifier);
        $record['tx_cpsshortnr_language'] = $language;
        $record['tx_cpsshortnr_language_parent'] = $languageParent;

        $contentObjectRenderer = GeneralUtility::makeInstance(ContentObjectRenderer::class);
        $contentObjectRenderer->start($record, $table);

        return $contentObjectRenderer->stdWrap('', $this->encodeFormat);
    }

    /**
     * @param string $table
     * @param array $record
     * @return string
     */
    private function findIdentifier($table, array $record)
    {
        $availableIdentifier = array_filter($this->configuration, function ($configuration) use ($table) {
            return $configuration['table'] === $table;
        });
        if (empty($availableIdentifier)) {
            return '';
        }

        if (count($availableIdentifier) === 1) {
            return key($availableIdentifier);
        }

        $defaultIdentifier = array_filter($availableIdentifier, function ($configuration) {
            return !isset($configuration['encodeMatchFields']);
        });

        $conditionalIdentifier = array_diff_key($availableIdentifier, $defaultIdentifier);

        if (empty($conditionalIdentifier)) {
            if (!empty($defaultIdentifier)) {
                return key($defaultIdentifier);
            }

            return '';
        }

        foreach ($conditionalIdentifier as $identifier => $configuration) {
            $encodeMatchFields = $configuration['encodeMatchFields'];
            $intersect = array_intersect_assoc($encodeMatchFields, $record);

            if ($intersect === $encodeMatchFields) {
                return $identifier;
            }
        }

        if (!empty($defaultIdentifier)) {
            return key($defaultIdentifier);
        }

        return '';
    }
}
