<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Ast\Pattern;

use CPSIT\ShortNr\Config\Ast\Nodes\AstNode;
use CPSIT\ShortNr\Config\Ast\Nodes\GroupNode;
use CPSIT\ShortNr\Config\Ast\Nodes\LiteralNode;
use CPSIT\ShortNr\Config\Ast\Nodes\SequenceNode;
use CPSIT\ShortNr\Config\Ast\Nodes\SubSequenceNode;
use CPSIT\ShortNr\Config\Ast\Pattern\Helper\PatternGroupCounter;
use CPSIT\ShortNr\Config\Ast\Pattern\Helper\PatternGroupCounterInterface;
use CPSIT\ShortNr\Config\Ast\Types\TypeRegistry;
use CPSIT\ShortNr\Exception\ShortNrPatternParseException;
use CPSIT\ShortNr\Exception\ShortNrPatternTypeException;

final class PatternParser
{
    private int $pos = 0;

    /**
     * @param TypeRegistry $typeRegistry
     * @param string $pattern
     * @param PatternGroupCounterInterface|null $groupCounter
     */
    public function __construct(
        private readonly TypeRegistry $typeRegistry,
        private readonly string       $pattern = '',
        private readonly ?PatternGroupCounterInterface $groupCounter = new PatternGroupCounter()
    )
    {}

    /**
     * Parse a pattern string into an AST
     */
    public function parse(?SequenceNode $rootNode = null): SequenceNode
    {
        $root = $rootNode ?? new SequenceNode();
        while ($this->pos < strlen($this->pattern)) {
            $node = $this->parseNext();
            if ($node !== null) {
                $root->addChild($node);
            }
        }

        return $root;
    }

    private function parseNext(): ?AstNode
    {
        if ($this->pos >= strlen($this->pattern)) {
            return null;
        }

        $char = $this->pattern[$this->pos];

        // Check for group
        if ($char === '{') {
            return $this->parseGroup();
        }

        // Check for optional section - parentheses always mark optional content
        if ($char === '(') {
            return $this->parseSubSequence();
        }

        // Parse literal text until next special character
        return $this->parseLiteral();
    }

    private function parseGroup(): AstNode
    {
        $start = $this->pos;
        $this->pos++; // Skip {

        $content = '';
        $depth = 1;

        while ($this->pos < strlen($this->pattern) && $depth > 0) {
            $char = $this->pattern[$this->pos];
            if ($char === '{') {
                $depth++;
                $content .= $char;
            } elseif ($char === '}') {
                $depth--;
                if ($depth > 0) {
                    $content .= $char;
                }
            } else {
                $content .= $char;
            }
            $this->pos++;
        }

        if ($depth > 0) {
            throw new ShortNrPatternParseException("Unclosed group", $this->pattern, $start);
        }

        // Parse group content
        if (!preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*):([a-zA-Z]+)(?:\(([^)]+)\))?$/', $content, $matches)) {
            throw new ShortNrPatternParseException("Invalid group syntax: $content", $this->pattern, $start);
        }

        $name = $matches[1];
        $type = $matches[2];
        $constraints = isset($matches[3]) ? $this->parseConstraints($matches[3]) : [];

        // VALIDATE TYPE EXISTS - fail fast during parsing
        if (!$this->typeRegistry->getType($type)) {
            throw new ShortNrPatternTypeException(
                "Unknown type '$type' in group '{$name}'",
                $type,
                $this->typeRegistry->getRegisteredTypes()
            );
        }

        // Create group node (always required - optionality handled by SubSequence wrapper)
        $node = new GroupNode($name, $type, $constraints, false);
        $node->setTypeRegistry($this->typeRegistry);
        // Assign group ID
        $node->setGroupId('g' . $this->groupCounter->increaseCounter());

        // Syntax normalization: {group}? → ({group})
        if ($this->pos < strlen($this->pattern) && $this->pattern[$this->pos] === '?') {
            $this->pos++;
            $subSequence = new SubSequenceNode();
            $subSequence->addChild($node);
            return $subSequence;
        }

        return $node;
    }

    private function parseSubSequence(): SequenceNode
    {
        $start = $this->pos;
        $this->pos++; // Skip (

        // Find matching closing parenthesis
        $depth = 1;
        $endPos = $this->pos;

        while ($endPos < strlen($this->pattern) && $depth > 0) {
            if ($this->pattern[$endPos] === '(') {
                $depth++;
            } elseif ($this->pattern[$endPos] === ')') {
                $depth--;
            }
            $endPos++;
        }

        if ($depth > 0) {
            throw new ShortNrPatternParseException("Unclosed optional subsequence", $this->pattern, $start);
        }

        // Extract content between parentheses
        $content = substr($this->pattern, $this->pos, $endPos - $this->pos - 1);

        // Parse the content recursively (but don't reset the group counter!)
        $parser = new PatternParser($this->typeRegistry, $content, $this->groupCounter);
        $node = $parser->parse(new SubSequenceNode());

        // SubSequenceNode is inherently optional by design - no need to mark it
        // The node type itself indicates that this section is optional

        // Jump straight to the endPos of the entire SubSection
        $this->pos = $endPos;

        return $node;
    }

    private function parseLiteral(): LiteralNode
    {
        $start = $this->pos;
        $literal = '';

        while ($this->pos < strlen($this->pattern)) {
            $char = $this->pattern[$this->pos];

            // Stop at special characters (parentheses and curly braces)
            if ($char === '{' || $char === '(') {
                break;
            }

            $literal .= $char;
            $this->pos++;
        }

        if ($literal === '') {
            throw new ShortNrPatternParseException("Empty literal", $this->pattern, $start);
        }

        return new LiteralNode($literal);
    }

    private function parseConstraints(string $constraintStr): array
    {
        $constraints = [];
        $pairs = $this->splitConstraints($constraintStr);

        foreach ($pairs as $pair) {
            $pair = trim($pair);
            if (str_contains($pair, '=')) {
                [$key, $value] = explode('=', $pair, 2);
                $constraints[trim($key)] = trim($value);
            }
        }

        return $constraints;
    }

    private function splitConstraints(string $constraintStr): array
    {
        $pairs = [];
        $current = '';
        $inQuotes = false;
        $escapeNext = false;

        for ($i = 0; $i < strlen($constraintStr); $i++) {
            $char = $constraintStr[$i];

            if ($escapeNext) {
                $current .= $char;
                $escapeNext = false;
                continue;
            }

            if ($char === '\\') {
                $current .= $char;
                $escapeNext = true;
                continue;
            }

            if ($char === '"') {
                $inQuotes = !$inQuotes;
                $current .= $char;
                continue;
            }

            if ($char === ',' && !$inQuotes) {
                if (trim($current) !== '') {
                    $pairs[] = trim($current);
                }
                $current = '';
                continue;
            }

            $current .= $char;
        }

        if (trim($current) !== '') {
            $pairs[] = trim($current);
        }

        return $pairs;
    }
}
