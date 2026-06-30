<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

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

    /** Current positional placeholder index (1-based count of placeholders). */
    private int $argIdx = 0;

    /**
     * Map of named argument name to the (1-based) positional placeholder indices
     * it occupies. MySQL `?` placeholders are not reusable, so a name may map to
     * several indices — one per occurrence.
     *
     * @var array<string, list<int>>
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

        return '?';
    }

    /**
     * Create a placeholder for a named argument. Positional `?` placeholders are
     * not reusable, so every occurrence of the same name gets its own placeholder
     * and arg slot; all of a name's slots are filled with the same value by
     * {@see QueryBuilder::withNamedArgs()}.
     */
    public function bindPlaceholder(string $name): string
    {
        $this->args[] = null;
        $this->argIdx++;
        $this->namedArgs[$name][] = $this->argIdx;

        return '?';
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
     * @return array<string, list<int>>
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
