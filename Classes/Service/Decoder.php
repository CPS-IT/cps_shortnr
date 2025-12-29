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

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\TypoScript\TypoScriptStringFactory;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Core\Log\LogLevel;

class Decoder
{
    /**
     * @var string
     */
    private $decodeIdentifier;

    /**
     * @var array
     */
    private $recordInformation;

    /**
     * @param string $shortlink
     * @param string $pattern
     */
    public function __construct(
        private array $configuration,
        private string $shortlink,
        private string $pattern
    )
    {
    }

    /**
     * @param string $configurationFile
     * @param string $shortlink
     * @param string $pattern
     * @throws \RuntimeException
     * @return Decoder
     */
    public static function createFromConfigurationFile($configurationFile, $shortlink, $pattern)
    {
        if (!file_exists($configurationFile)) {
            throw new \RuntimeException('Configuration file not found', 1490608823);
        }

        $file = GeneralUtility::getUrl($configurationFile);
        if (empty($file)) {
            throw new \RuntimeException('Configuration file could not be read', 1490608852);
        }

        $typoScriptArray = GeneralUtility::makeInstance(TypoScriptStringFactory::class)
            ->parseFromStringWithIncludes(
                'cps_shortnr_configuration',
                $file
            )
            ->toArray();


        if (!isset($typoScriptArray['cps_shortnr.'])) {
            throw new \RuntimeException('No "cps_shortnr" configuration found', 1490608923);
        }

        return new self($typoScriptArray['cps_shortnr.'], $shortlink, $pattern);
    }

    /**
     * @return array
     */
    public function getShortlinkParts()
    {
        $regularExpression = $this->pattern;
        $regularExpression = str_replace('/', '\\/', $regularExpression);

        $matches = [];
        preg_match('/' . $regularExpression . '/', $this->shortlink, $matches);

        return $matches;
    }

    public function getRecordInformation(): array
    {
        $loger = GeneralUtility::makeInstance(LogManager::class)->getLogger(self::class);

        if ($this->decodeIdentifier === null) {
            $this->resolveDecodeIdentifier();
        }

        if (empty($this->configuration[$this->decodeIdentifier . '.'])) {

            $msg = 'Missing shortlink configuration for key' . $this->decodeIdentifier . ' 1490608891';
            $loger->log(LogLevel::ERROR, $msg, []);
            return [];
        }

        $shortLinkConfiguration = $this->configuration[$this->decodeIdentifier . '.'];

        if (empty($shortLinkConfiguration['source.'])
            || (empty($shortLinkConfiguration['source.']['record']) && empty($shortLinkConfiguration['source.']['record.']))
            || empty($shortLinkConfiguration['source.']['table'])
            || empty($shortLinkConfiguration['path.'])
        ) {
            $msg = 'Invalid shortlink configuration 1490608898';
            $loger->log(LogLevel::ERROR, $msg, []);
            return [];
        }

        // Get record
        if (empty($shortLinkConfiguration['source.']['record.'])) {
            $recordUid = (int)$shortLinkConfiguration['source.']['record'];
        } else {
            $contentObjectRenderer = GeneralUtility::makeInstance(ContentObjectRenderer::class);
            $recordUid = (int)$contentObjectRenderer->stdWrap(
                $shortLinkConfiguration['source.']['record'] ?? '',
                $shortLinkConfiguration['source.']['record.']
            );
        }

        $table = $shortLinkConfiguration['source.']['table'];
        $record = BackendUtility::getRecord($table, $recordUid);

        if ($record === null) {
            $msg = 'No record for ' . $recordUid . ' found 1490609023';
            $loger->log(LogLevel::ERROR, $msg, []);
            return [];

        }

        $this->recordInformation = [
            'record' => $record,
            'table' => $table,
        ];

        return $this->recordInformation;
    }

    /**
     * @return string
     */
    public function getPath(ServerRequestInterface $request)
    {
        if ($this->recordInformation === null) {
            $this->getRecordInformation();
        }


        $contentObjectRenderer = GeneralUtility::makeInstance(ContentObjectRenderer::class);
        $contentObjectRenderer->setRequest($request);
        $contentObjectRenderer->start($this->recordInformation['record'], $this->recordInformation['table']);


        return $contentObjectRenderer->stdWrap('', $this->configuration[$this->decodeIdentifier . '.']['path.']);
    }

    private function resolveDecodeIdentifier(): void
    {
        // Get decode information and configuration
        if (empty($this->configuration['decoder']) && empty($this->configuration['decoder.'])) {
            throw new \RuntimeException('Missing key configuration', 1490608877);
        }

        if (empty($this->configuration['decoder.'])) {
            $this->decodeIdentifier = strtolower((string) $this->configuration['decoder']);
        } else {
            $contentObjectRenderer = GeneralUtility::makeInstance(ContentObjectRenderer::class);
            $this->decodeIdentifier = strtolower($contentObjectRenderer->stdWrap(
                $this->configuration['decoder'] ?? '',
                $this->configuration['decoder.']
            ));
        }
    }
}
