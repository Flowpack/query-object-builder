<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * Accumulates the generated SQL string together with positional/named arguments
 * and any errors that occur while building. An instance is threaded through
 * {@see SqlWriter::writeSql()} while building a query.
 *
 * This is the one mutable part of the model: a short-lived rendering accumulator.
 *
 * @internal
 */
final class SqlBuilder
{
    private string $sql = '';

    /**
     * Positional arguments collected via {@see createPlaceholder()} / {@see bindPlaceholder()}.
     * Named placeholders add a `null` slot to be filled in later.
     *
     * @var array<int, mixed>
     */
    private array $args = [];

    /** Current positional placeholder index. */
    private int $argIdx = 0;

    /**
     * Map of named argument name to its (1-based) positional placeholder index.
     *
     * @var array<string, int>
     */
    private array $namedArgs = [];

    /** @var list<\Throwable> */
    private array $errors = [];

    public function __construct(
        private readonly bool $validating = true,
    ) {
    }

    public function writeString(string $s): void
    {
        $this->sql .= $s;
    }

    /**
     * Create a new positional placeholder bound to the given argument value.
     */
    public function createPlaceholder(mixed $argument): string
    {
        $this->args[] = $argument;
        $this->argIdx++;

        return '$' . $this->argIdx;
    }

    /**
     * Create (or reuse) a named placeholder. Calling it again with the same name
     * reuses the same positional placeholder. The argument value is bound later
     * by {@see QueryBuilder::withNamedArgs()}.
     */
    public function bindPlaceholder(string $name): string
    {
        if (!isset($this->namedArgs[$name])) {
            // Add an empty argument slot, it will be replaced later by the named argument.
            $this->args[] = null;
            $this->argIdx++;
            $this->namedArgs[$name] = $this->argIdx;
        }

        return '$' . $this->namedArgs[$name];
    }

    public function isValidating(): bool
    {
        return $this->validating;
    }

    public function addError(\Throwable $error): void
    {
        $this->errors[] = $error;
    }

    public function getSql(): string
    {
        return $this->sql;
    }

    /**
     * @return array<int, mixed>
     */
    public function getArgs(): array
    {
        return $this->args;
    }

    /**
     * @return array<string, int>
     */
    public function getNamedArgs(): array
    {
        return $this->namedArgs;
    }

    /**
     * @return list<\Throwable>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
