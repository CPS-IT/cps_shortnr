<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Event;

/**
 * Event for manipulating the final loaded and merged configuration
 *
 * This event is dispatched after all configuration files have been loaded
 * and merged but before the configuration is compiled and cached.
 * It allows extensions to manipulate the final configuration array.
 */
final class ShortNrConfigLoadedEvent
{
    /**
     * @param array $configuration The merged configuration array
     */
    public function __construct(
        private array $configuration
    ) {}

    /**
     * Get the current configuration
     *
     * @return array
     */
    public function getConfiguration(): array
    {
        return $this->configuration;
    }

    /**
     * Set the entire configuration
     *
     * @param array $configuration
     */
    public function setConfiguration(array $configuration): void
    {
        $this->configuration = $configuration;
    }
}