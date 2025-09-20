<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\DTO;

use CPSIT\ShortNr\Exception\ShortNrConfigException;
use BackedEnum;
use TypedPatternEngine\Compiler\CompiledPattern;

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
     * Get plugin-specific configuration (not yet implemented)
     *
     * @return array|null Plugin configuration array
     */
    public function getPluginConfig(): ?array;

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

    /**
     * @return CompiledPattern
     */
    public function getPattern(): CompiledPattern;

    /**
     * @return array
     */
    public function getCondition(): array;
}
