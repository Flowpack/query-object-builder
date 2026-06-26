<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * Entry point for turning a {@see SqlWriter} into an SQL string with bound
 * arguments.
 *
 * This mirrors the Go `builder.QueryBuilder`: the actual SQL generation is
 * driver-independent, drivers only differ in how the resulting SQL and
 * arguments are executed.
 */
final class QueryBuilder
{
    /** @var array<string, mixed> */
    private array $namedArgs = [];

    private bool $validating = true;

    public function __construct(
        private readonly SqlWriter $writer,
    ) {
    }

    /**
     * Start a new query builder based on the given SqlWriter.
     */
    public static function build(SqlWriter $writer): self
    {
        return new self($writer);
    }

    /**
     * Generate the SQL and the list of positional arguments.
     *
     * @return array{0: string, 1: array<int, mixed>}
     * @throws QueryBuilderException if building the query failed
     */
    public function toSql(): array
    {
        $sb = new SqlBuilder($this->validating);

        if ($this->writer instanceof InnerSqlWriter) {
            $this->writer->innerWriteSql($sb);
        } else {
            $this->writer->writeSql($sb);
        }

        $sql = $sb->getSql();
        $args = $sb->getArgs();

        foreach ($sb->getNamedArgs() as $name => $argIdx) {
            if (!array_key_exists($name, $this->namedArgs)) {
                throw new QueryBuilderException(sprintf('missing named argument "%s"', $name));
            }
            $args[$argIdx - 1] = $this->namedArgs[$name];
        }

        $errors = $sb->getErrors();
        if ($errors !== []) {
            $message = implode("\n", array_map(static fn (\Throwable $e): string => $e->getMessage(), $errors));
            throw new QueryBuilderException($message, 0, $errors[0]);
        }

        return [$sql, $args];
    }

    /**
     * Bind values for named placeholders (created via {@see SqlBuilder::bindPlaceholder()}).
     *
     * @param array<string, mixed> $args
     */
    public function withNamedArgs(array $args): self
    {
        $this->namedArgs = $args;

        return $this;
    }

    /**
     * Disable validation (e.g. of identifiers) while building.
     */
    public function withoutValidation(): self
    {
        $this->validating = false;

        return $this;
    }
}
