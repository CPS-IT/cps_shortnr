<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Ast\Nodes;

use CPSIT\ShortNr\Config\Ast\Types\TypeRegistry;

final class LiteralNode extends NamedAstNode
{
    public function __construct(
        private readonly string $text
    ) {}

    /**
     * @inheritDoc
     */
    protected function generateRegex(): string
    {
        return preg_quote($this->text, '/');
    }

    public function generate(array $values): string
    {
        return $this->text;
    }

    public function getGroupNames(): array
    {
        return [];
    }

    public function hasOptional(): bool
    {
        return false;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function getNodeType(): string
    {
        return 'literal';
    }

    public function toArray(): array
    {
        return [
            ...parent::toArray(),
            'type' => $this->getNodeType(),
            'text' => $this->text
        ];
    }

    public static function fromArray(array $data, ?TypeRegistry $typeRegistry = null): static
    {
        $node = new self($data['text']);
        $node->setRegex($data['regex'] ?? null);
        return $node;
    }
}
