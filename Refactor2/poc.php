<?php
declare(strict_types=1);

/**
 * Advanced Pattern to Regex Compiler and Parser
 * PHP 8.1+ Implementation with AST and Reverse Compilation
 */

// AST Node Types
abstract class AstNode {
    abstract public function toRegex(): string;
    abstract public function generate(array $values): string;
}

class LiteralNode extends AstNode {
    public function __construct(
        private string $text
    ) {}

    public function toRegex(): string {
        $specialChars = ['.', '^', '$', '*', '+', '?', '[', ']', '\\', '/', '|', '(', ')'];
        $escaped = '';
        for ($i = 0; $i < strlen($this->text); $i++) {
            $char = $this->text[$i];
            if (in_array($char, $specialChars)) {
                $escaped .= '\\' . $char;
            } else {
                $escaped .= $char;
            }
        }
        return $escaped;
    }

    public function generate(array $values): string {
        return $this->text;
    }

    public function getText(): string {
        return $this->text;
    }
}

class GroupNode extends AstNode {
    private string $groupId;

    public function __construct(
        private string $name,
        private string $type,
        private array $constraints = [],
        private bool $optional = false
    ) {}

    public function setGroupId(string $id): void {
        $this->groupId = $id;
    }

    public function getGroupId(): string {
        return $this->groupId ?? '';
    }

    public function getName(): string {
        return $this->name;
    }

    public function getType(): string {
        return $this->type;
    }

    public function getConstraints(): array {
        return $this->constraints;
    }

    public function isOptional(): bool {
        return $this->optional;
    }

    public function toRegex(): string {
        $pattern = $this->generateTypeRegex();
        $regex = '(?P<' . $this->groupId . '>' . $pattern . ')';
        return $this->optional ? $regex . '?' : $regex;
    }

    public function generate(array $values): string {
        if (!isset($values[$this->name])) {
            return $this->optional ? '' : throw new \RuntimeException("Missing required value for: {$this->name}");
        }
        return (string)$values[$this->name];
    }

    private function generateTypeRegex(): string {
        return match($this->type) {
            'int' => '\d+',
            'string' => $this->generateStringRegex(),
            'alpha' => '[a-zA-Z]+',
            'alnum' => '[a-zA-Z0-9]+',
            'uuid' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}',
            'email' => '[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}',
            'slug' => '[a-z0-9]+(?:-[a-z0-9]+)*',
            default => '[^/]+',
        };
    }

    private function generateStringRegex(): string {
        $minLen = $this->constraints['minLen'] ?? null;
        $maxLen = $this->constraints['maxLen'] ?? null;
        $pattern = $this->constraints['pattern'] ?? null;

        if ($pattern) {
            return $pattern;
        }

        if ($minLen !== null && $maxLen !== null) {
            return "[^/]{{$minLen},{$maxLen}}";
        } elseif ($minLen !== null) {
            return "[^/]{{$minLen},}";
        } elseif ($maxLen !== null) {
            return "[^/]{0,{$maxLen}}";
        }

        return '[^/]+';
    }
}

class OptionalSectionNode extends AstNode {
    /** @var AstNode[] */
    private array $children = [];

    public function addChild(AstNode $node): void {
        $this->children[] = $node;
    }

    public function getChildren(): array {
        return $this->children;
    }

    public function toRegex(): string {
        $regex = '';
        foreach ($this->children as $child) {
            $regex .= $child->toRegex();
        }
        return '(?:' . $regex . ')?';
    }

    public function generate(array $values): string {
        // Check if any group in this optional section has a value
        $hasAnyValue = false;
        foreach ($this->children as $child) {
            if ($child instanceof GroupNode && isset($values[$child->getName()])) {
                $hasAnyValue = true;
                break;
            }
        }

        if (!$hasAnyValue) {
            return '';
        }

        $result = '';
        foreach ($this->children as $child) {
            $result .= $child->generate($values);
        }
        return $result;
    }
}

class SequenceNode extends AstNode {
    /** @var AstNode[] */
    private array $children = [];

    public function addChild(AstNode $node): void {
        $this->children[] = $node;
    }

    public function getChildren(): array {
        return $this->children;
    }

    public function toRegex(): string {
        $regex = '';
        foreach ($this->children as $child) {
            $regex .= $child->toRegex();
        }
        return $regex;
    }

    public function generate(array $values): string {
        $result = '';
        foreach ($this->children as $child) {
            $result .= $child->generate($values);
        }
        return $result;
    }
}

class PatternParser {
    private int $pos = 0;
    private string $pattern = '';
    private int $groupCounter = 0;
    private static int $globalGroupCounter = 0;

    public function parse(string $pattern, bool $resetCounter = true): SequenceNode {
        $this->pattern = $pattern;
        $this->pos = 0;

        if ($resetCounter) {
            self::$globalGroupCounter = 0;
        }
        $this->groupCounter = &self::$globalGroupCounter;

        $root = new SequenceNode();

        while ($this->pos < strlen($this->pattern)) {
            $node = $this->parseNext();
            if ($node !== null) {
                $root->addChild($node);
            }
        }

        return $root;
    }

    private function parseNext(): ?AstNode {
        if ($this->pos >= strlen($this->pattern)) {
            return null;
        }

        $char = $this->pattern[$this->pos];

        // Check for group
        if ($char === '{') {
            return $this->parseGroup();
        }

        // Check for optional section
        if ($char === '(') {
            $section = $this->parseOptionalSection();
            if ($section !== null) {
                return $section;
            }
            // If not an optional section, treat ( as literal
            $this->pos++;
            return new LiteralNode('(');
        }

        // Parse literal text until next special character
        return $this->parseLiteral();
    }

    private function parseGroup(): GroupNode {
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

        // Check for optional marker
        $optional = false;
        if ($this->pos < strlen($this->pattern) && $this->pattern[$this->pos] === '?') {
            $optional = true;
            $this->pos++;
        }

        // Parse group content
        if (!preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*):([a-zA-Z]+)(?:\(([^)]+)\))?$/', $content, $matches)) {
            throw new \InvalidArgumentException("Invalid group syntax: {$content}");
        }

        $name = $matches[1];
        $type = $matches[2];
        $constraints = isset($matches[3]) ? $this->parseConstraints($matches[3]) : [];

        $node = new GroupNode($name, $type, $constraints, $optional);

        // Assign group ID
        $this->groupCounter++;
        $node->setGroupId('g' . $this->groupCounter);

        return $node;
    }

    private function parseOptionalSection(): ?OptionalSectionNode {
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

        // Check if followed by ?
        if ($endPos >= strlen($this->pattern) || $this->pattern[$endPos] !== '?') {
            // Not an optional section, reset position
            $this->pos = $start;
            return null;
        }

        // Extract content between parentheses
        $content = substr($this->pattern, $this->pos, $endPos - $this->pos - 1);

        // Parse the content recursively (but don't reset the group counter!)
        $parser = new PatternParser();
        $innerAst = $parser->parse($content, false); // false = don't reset counter

        // Create optional section node
        $section = new OptionalSectionNode();
        foreach ($innerAst->getChildren() as $child) {
            $section->addChild($child);
        }

        // Update position past the )?
        $this->pos = $endPos + 1;

        return $section;
    }

    private function parseLiteral(): LiteralNode {
        $start = $this->pos;
        $literal = '';

        while ($this->pos < strlen($this->pattern)) {
            $char = $this->pattern[$this->pos];

            // Stop at special characters
            if ($char === '{' || ($char === '(' && $this->isOptionalSection())) {
                break;
            }

            $literal .= $char;
            $this->pos++;
        }

        return new LiteralNode($literal);
    }

    private function isOptionalSection(): bool {
        // Look ahead to check if this is an optional section
        $tempPos = $this->pos + 1;
        $depth = 1;

        while ($tempPos < strlen($this->pattern) && $depth > 0) {
            if ($this->pattern[$tempPos] === '(') {
                $depth++;
            } elseif ($this->pattern[$tempPos] === ')') {
                $depth--;
                if ($depth === 0) {
                    // Check if followed by ?
                    return ($tempPos + 1 < strlen($this->pattern) &&
                        $this->pattern[$tempPos + 1] === '?');
                }
            }
            $tempPos++;
        }

        return false;
    }

    private function parseConstraints(string $constraintStr): array {
        $constraints = [];
        $pairs = explode(',', $constraintStr);

        foreach ($pairs as $pair) {
            $pair = trim($pair);
            if (strpos($pair, '=') !== false) {
                [$key, $value] = explode('=', $pair, 2);
                $constraints[trim($key)] = trim($value);
            }
        }

        return $constraints;
    }
}

class PatternCompiler {
    private SequenceNode $ast;
    private array $namedGroups = [];
    private array $groupTypes = [];
    private array $groupConstraints = [];

    public function compile(string $pattern): CompiledPattern {
        $parser = new PatternParser();
        $this->ast = $parser->parse($pattern);

        // Extract group information from AST
        $this->extractGroupInfo($this->ast);

        // Generate regex from AST
        $regex = '/^' . $this->ast->toRegex() . '$/';

        return new CompiledPattern(
            $regex,
            $this->ast,
            $this->namedGroups,
            $this->groupTypes,
            $this->groupConstraints
        );
    }

    private function extractGroupInfo(AstNode $node): void {
        if ($node instanceof GroupNode) {
            $groupId = $node->getGroupId();
            $this->namedGroups[$groupId] = $node->getName();
            $this->groupTypes[$node->getName()] = $node->getType();
            $this->groupConstraints[$node->getName()] = $node->getConstraints();
        } elseif ($node instanceof SequenceNode || $node instanceof OptionalSectionNode) {
            foreach ($node->getChildren() as $child) {
                $this->extractGroupInfo($child);
            }
        }
    }
}

class CompiledPattern {
    public function __construct(
        private string $regex,
        private SequenceNode $ast,
        private array $namedGroups,
        private array $groupTypes,
        private array $groupConstraints
    ) {}

    public function getRegex(): string {
        return $this->regex;
    }

    public function getAst(): SequenceNode {
        return $this->ast;
    }

    public function match(string $input): ?MatchResult {
        if (!preg_match($this->regex, $input, $matches)) {
            return null;
        }

        $result = new MatchResult($input);

        foreach ($this->namedGroups as $groupId => $groupName) {
            if (isset($matches[$groupId]) && $matches[$groupId] !== '') {
                $value = $matches[$groupId];
                $type = $this->groupTypes[$groupName];
                $constraints = $this->groupConstraints[$groupName];

                // Validate and convert value based on type
                $processedValue = $this->processValue($value, $type, $constraints);

                if ($processedValue !== null) {
                    $result->addGroup($groupName, $processedValue, $type, $constraints);
                }
            }
        }

        return $result;
    }

    public function generate(array $values): string {
        // Extract just the values if we're given the full group data
        $cleanValues = [];
        foreach ($values as $key => $value) {
            if (is_array($value) && isset($value['value'])) {
                $cleanValues[$key] = $value['value'];
            } else {
                $cleanValues[$key] = $value;
            }
        }
        return $this->ast->generate($cleanValues);
    }

    private function processValue(string $value, string $type, array $constraints): mixed {
        return match($type) {
            'int' => $this->processInt($value, $constraints),
            default => $value
        };
    }

    private function processInt(string $value, array $constraints): ?int {
        $intValue = (int)$value;

        if (isset($constraints['min']) && $intValue < (int)$constraints['min']) {
            return null;
        }

        if (isset($constraints['max']) && $intValue > (int)$constraints['max']) {
            return null;
        }

        return $intValue;
    }
}

class MatchResult {
    private array $groups = [];

    public function __construct(
        private string $input
    ) {}

    public function addGroup(string $name, mixed $value, string $type, array $constraints): void {
        $this->groups[$name] = [
            'value' => $value,
            'type' => $type,
            'constraints' => $constraints
        ];
    }

    public function get(string $name): mixed {
        return $this->groups[$name]['value'] ?? null;
    }

    public function getGroups(): array {
        return $this->groups;
    }

    public function getInput(): string {
        return $this->input;
    }

    public function toArray(): array {
        $result = ['input' => $this->input];
        foreach ($this->groups as $name => $data) {
            $result[$name] = $data['value'];
        }
        return $result;
    }
}

// Example usage and tests
function demonstrateUsage(): void {
    $t = microtime(true);
    $compiler = new PatternCompiler();
    $t1 = (microtime(true)-$t)* 1000;
    echo "init object: $t1 ms\n";

    // Test 1: Original Pattern with Reverse Compilation
    echo "Test 1: Pattern with Optional Section & Reverse Compilation\n";
    echo "============================================================\n";
    $pattern1 = "PAGE{uid:int(min=0, max=9999999)}(-{langId:int})?";
    $t = microtime(true);
    $compiled1 = $compiler->compile($pattern1);
    $t1 = (microtime(true)-$t)* 1000;
    echo "compile \"$pattern1\": $t1 ms\n";
    echo "Regex: " . $compiled1->getRegex() . "\n\n";

    // Test matching
    $tests = ["PAGE123", "PAGE123-1", "PAGE999999-42"];
    foreach ($tests as $test) {
        $t = microtime(true);
        $t2 = microtime(true);
        $result = $compiled1->match($test);
        $t2 = (microtime(true)-$t2)* 1000;
        if ($result) {
            echo "✓ '$test' matched ($t2 ms):\n";
            print_r($result->toArray());

            // Test reverse compilation
            $t2 = microtime(true);
            $generated = $compiled1->generate($result->getGroups());
            $t2 = (microtime(true)-$t2)* 1000;
            echo "  Reverse generated ($t2 ms): '$generated'\n";
        }
        echo "\n";
        $t1 = (microtime(true)-$t)* 1000;
        echo "test \"$test\" complete in: $t1 ms\n";
    }

    // Test generation with custom values
    echo "Generating from values:\n";
    $generated1 = $compiled1->generate(['uid' => 456]);
    echo "  {uid: 456} → '$generated1'\n";

    $generated2 = $compiled1->generate(['uid' => 789, 'langId' => 2]);
    echo "  {uid: 789, langId: 2} → '$generated2'\n\n";

    // Test 2: Complex Pattern with multiple optional sections
    echo "\nTest 2: Multiple Optional Sections Anywhere\n";
    echo "============================================\n";
    $pattern2 = "(prefix-)?user/{id:int}(-{status:alpha})?(/edit)?";
    $t = microtime(true);
    $compiled2 = $compiler->compile($pattern2);
    $t1 = (microtime(true)-$t)* 1000;
    echo "compile \"$pattern2\": $t1 ms\n";
    echo "Regex: " . $compiled2->getRegex() . "\n\n";

    $tests2 = [
        "user/123",
        "prefix-user/123",
        "user/123-active",
        "user/123/edit",
        "prefix-user/123-active/edit"
    ];

    foreach ($tests2 as $test) {
        $t = microtime(true);
        $t2 = microtime(true);
        $result = $compiled2->match($test);
        $t2 = (microtime(true)-$t2)* 1000;
        if ($result) {
            echo "✓ '$test' matched ($t2 ms)\n";
            $t2 = microtime(true);
            $generated = $compiled2->generate($result->getGroups());
            $t2 = (microtime(true)-$t2)* 1000;
            echo "  Reverse ($t2 ms): '$generated'\n";
        }
        $t1 = (microtime(true)-$t)* 1000;
        echo "test \"$test\" complete in: $t1 ms\n";
    }

    // Test 3: Nested optional sections
    echo "\nTest 3: Nested Optional Sections\n";
    echo "=================================\n";
    $pattern3 = "doc/{id:int}(-v{version:int}(-{status:alpha})?)?";
    $t = microtime(true);
    $compiled3 = $compiler->compile($pattern3);
    $t1 = (microtime(true)-$t)* 1000;
    echo "compile \"$pattern3\": $t1 ms\n";
    echo "Regex: " . $compiled3->getRegex() . "\n\n";

    $tests3 = [
        "doc/100",
        "doc/100-v2",
        "doc/100-v2-draft"
    ];

    foreach ($tests3 as $test) {
        $t = microtime(true);
        $result = $compiled3->match($test);
        if ($result) {
            echo "✓ '$test' matched\n";
            $values = $result->toArray();
            unset($values['input']);
            $generated = $compiled3->generate($values);
            echo "  Reverse: '$generated'\n";
        }
        $t1 = (microtime(true)-$t)* 1000;
        echo "test \"$test\" complete in: $t1 ms\n";
    }

    // Test 4: Optional section in the middle
    echo "\nTest 4: Optional Section in Middle of Pattern\n";
    echo "==============================================\n";
    $pattern4 = "api/v{version:int}(/beta)?/{resource:alpha}/{id:int}?";
    $t = microtime(true);
    $compiled4 = $compiler->compile($pattern4);
    $t1 = (microtime(true)-$t)* 1000;
    echo "compile \"$pattern3\": $t1 ms\n";

    echo "Regex: " . $compiled4->getRegex() . "\n\n";

    $tests4 = [
        "api/v1/users/123",
        "api/v2/beta/posts/456",
        "api/v1/users"
    ];

    foreach ($tests4 as $test) {
        $t = microtime(true);
        $result = $compiled4->match($test);
        if ($result) {
            echo "✓ '$test' matched\n";
            $values = $result->toArray();
            unset($values['input']);
            $generated = $compiled4->generate($values);
            echo "  Reverse: '$generated'\n";
        }
        $t1 = (microtime(true)-$t)* 1000;
        echo "test \"$test\" complete in: $t1 ms\n";
    }
}

// Run demonstration
demonstrateUsage();
