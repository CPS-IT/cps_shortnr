<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\DTO;

use CPSIT\ShortNr\Config\Enums\ConfigEnum;
use CPSIT\ShortNr\Exception\ShortNrConfigException;
use BackedEnum;

class ConfigItem implements ConfigItemInterface
{
    /**
     * Create a scoped config accessor for a specific config item
     *
     * @param string $name Config item name to scope to
     * @param ConfigInterface $config Global config instance to delegate to
     */
    public function __construct(
        private readonly string $name,
        private readonly ConfigInterface $config
    ) {}

    /**
     * {@inheritDoc}
     */
    public function getValue(string|BackedEnum $configField): ?string
    {
        return $this->config->getValue($this->name, $configField) ?? null;
    }

    /**
     * {@inheritDoc}
     */
    public function getPrefixMatch(): string
    {
        return $this->config->getValue($this->name, ConfigEnum::PrefixMatch);
    }

    /**
     * {@inheritDoc}
     */
    public function getPriority(): int
    {
        return (int)$this->config->getValue($this->name, ConfigEnum::Priority);
    }

    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * {@inheritDoc}
     */
    public function getConfig(): ConfigInterface
    {
        return $this->config;
    }

    /**
     * {@inheritDoc}
     */
    public function getRegex(): ?string
    {
        return $this->config->getValue($this->name, ConfigEnum::Regex);
    }

    /**
     * {@inheritDoc}
     */
    public function getPrefix(): ?string
    {
        return $this->config->getValue($this->name, ConfigEnum::Prefix);
    }

    /**
     * {@inheritDoc}
     */
    public function getType(): ?string
    {
        return $this->config->getValue($this->name, ConfigEnum::Type);
    }

    /**
     * {@inheritDoc}
     */
    public function getTableName(): ?string
    {
        return $this->config->getValue($this->name, ConfigEnum::Table);
    }

    /**
     * {@inheritDoc}
     */
    public function getCondition(): array
    {
        return $this->config->getValue($this->name, ConfigEnum::Condition) ?? [];
    }

    /**
     * {@inheritDoc}
     */
    public function getPluginConfig(): array
    {
        throw new ShortNrConfigException('Plugin configuration not yet implemented');
    }

    /**
     * {@inheritDoc}
     */
    public function getNotFound(): ?string
    {
        return $this->config->getValue($this->name, ConfigEnum::NotFound);
    }

    /**
     * {@inheritDoc}
     */
    public function getLanguageParentField(): ?string
    {
        return $this->config->getValue($this->name, ConfigEnum::LanguageParentField);
    }

    /**
     * {@inheritDoc}
     */
    public function getLanguageField(): ?string
    {
        return $this->config->getValue($this->name, ConfigEnum::LanguageField);
    }

    /**
     * {@inheritDoc}
     */
    public function getRecordIdentifier(): ?string
    {
        return $this->config->getValue($this->name, ConfigEnum::IdentifierField);
    }

    /**
     * {@inheritDoc}
     */
    public function canLanguageOverlay(): bool
    {
        return !empty($this->getRecordIdentifier()) && !empty($this->getLanguageField());
    }
}
