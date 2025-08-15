<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Ast\Compiler;

use Throwable;

final class MatchResult
{
    private array $groups = [];
    private array $errors = [];

    public function __construct(
        private readonly string $input
    ) {}

    public function addGroup(string $name, mixed $value, string $type, array $constraints): void
    {
        $this->groups[$name] = [
            'value' => $value,
            'type' => $type,
            'constraints' => $constraints
        ];
    }

    /**
     * @return bool
     */
    public function isFailed(): bool
    {
        return !empty($this->errors);
    }

    /**
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * @param Throwable $error
     */
    public function addError(Throwable $error): void
    {
        $this->errors[] = $error;
    }

    public function get(string $name): mixed
    {
        return $this->groups[$name]['value'] ?? null;
    }

    public function getGroups(): array
    {
        return $this->groups;
    }

    public function getInput(): string
    {
        return $this->input;
    }

    public function toArray(): array
    {
        $result = ['input' => $this->input];
        foreach ($this->groups as $name => $data) {
            $result[$name] = $data['value'];
        }
        return $result;
    }
}
