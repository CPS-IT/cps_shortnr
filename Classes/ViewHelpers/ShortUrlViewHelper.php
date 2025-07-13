<?php declare(strict_types=1);

namespace CPSIT\ShortNr\ViewHelpers;

use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

final class ShortUrlViewHelper extends AbstractViewHelper
{
    protected $escapeOutput = false;

    public function initializeArguments(): void
    {
        $this->registerArgument(
            'target',
            'int',
            'Page UID or target identifier',
            true
        );
        $this->registerArgument(
            'type',
            'string',
            'Route type from config (pages, press, etc.)',
            false,
            'pages'
        );
        $this->registerArgument(
            'language',
            'int',
            'Language UID for multilingual URLs',
            false
        );
        $this->registerArgument(
            'absolute',
            'bool',
            'Generate absolute URL',
            false,
            false
        );
        $this->registerArgument(
            'parameters',
            'array',
            'Additional parameters for plugins',
            false,
            []
        );
    }

    public function render(): string
    {
        $target = $this->arguments['target'];
        $type = $this->arguments['type'] ?? 'pages';
        $language = $this->arguments['language'] ?? null;
        $absolute = $this->arguments['absolute'] ?? false;
        $parameters = $this->arguments['parameters'] ?? [];

        return 'TODO: RETURN SHORT URL';
    }
}