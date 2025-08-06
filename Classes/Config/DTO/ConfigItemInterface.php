<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\DTO;

use CPSIT\ShortNr\Exception\ShortNrConfigException;
use BackedEnum;

interface ConfigItemInterface
{
    /**
     * Get Custom Config Information like user defined custom config
     *
     * @param string|BackedEnum $configField
     * @return string|null
     */
    public function getValue(string|BackedEnum $configField): ?string;

    /**
     * Get the config Item PrefixMatch Value like: "{match-1}" so we know what match value is the prefix
     *
     * @return string
     */
    public function getPrefixMatch(): string;

    /**
     * Get the config item name this instance is scoped to
     *
     * @return string Config name like 'pages', 'plugins', 'events'
     */
    public function getName(): string;

    /**
     * Get access to the global config for operations outside this item's scope
     *
     * @return ConfigInterface Global config instance
     */
    public function getConfig(): ConfigInterface;

    // Route Pattern Properties

    /**
     * Get the regex pattern for URL matching
     *
     * Falls back to _default if not set for this config item.
     * Used by the decoder to match short URLs to this config.
     *
     * @return string|null Regex pattern like '/^PAGE(\d+)$/' or null if not configured
     */
    public function getRegex(): ?string;

    /**
     * Get the URL prefix for this config type
     *
     * @return string|null Prefix like 'PAGE', 'PLUGIN' or null if not configured
     */
    public function getPrefix(): ?string;

    // Processor Configuration

    /**
     * Get the processor type that should handle this config
     *
     * Determines which processor class processes URLs for this config.
     *
     * @return string|null Processor type like 'page', 'plugin' or null if not configured
     */
    public function getType(): ?string;

    /**
     * Get the database table name for record lookups
     *
     * @return string|null Table name like 'pages', 'tt_content' or null if not configured
     */
    public function getTableName(): ?string;

    /**
     * Get the database conditions for record filtering
     *
     * Contains field mappings and values for building SQL WHERE clauses.
     * Supports placeholder replacement like '{match-1}' from regex captures.
     *
     * @return array Condition array like ['uid' => '{match-1}', 'hidden' => 0]
     */
    public function getCondition(): array;

    /**
     * Get plugin-specific configuration (not yet implemented)
     *
     * @return array Plugin configuration array
     * @throws ShortNrConfigException Until implementation is complete
     */
    public function getPluginConfig(): array;

    /**
     * Get the fallback URL/page for not found cases
     *
     * Can be either a complete URL or a page UID for recursive resolution.
     *
     * @return string|null Fallback URL like '/404' or page UID like '1'
     */
    public function getNotFound(): ?string;

    // Language/Overlay Configuration

    /**
     * Get the database field name for language parent relationships
     *
     * Used for TYPO3 language overlay resolution (typically 'l10n_parent').
     *
     * @return string|null Field name or null if language overlay not configured
     */
    public function getLanguageParentField(): ?string;

    /**
     * Get the database field name for language identification
     *
     * Used for TYPO3 language overlay resolution (typically 'sys_language_uid').
     *
     * @return string|null Field name or null if language overlay not configured
     */
    public function getLanguageField(): ?string;

    /**
     * Get the database field name used as primary identifier
     *
     * Used for record lookups and language overlay (typically 'uid').
     *
     * @return string|null Field name or null if not configured
     */
    public function getRecordIdentifier(): ?string;

    /**
     * Check if this config supports TYPO3 language overlay processing
     *
     * Returns true if both record identifier and language field are configured.
     *
     * @return bool True if language overlay is supported
     */
    public function canLanguageOverlay(): bool;
}
