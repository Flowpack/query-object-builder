<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * Turns a query into an SQL string with its bound positional arguments.
 */
final class QueryBuilder
{
    /**
     * @param array<string, mixed> $namedArgs
     */
    public function __construct(
        private readonly SqlWriter $writer,
        private readonly array $namedArgs = [],
        private readonly bool $validating = true,
    ) {
    }

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

        // A top-level query is written without the parentheses it would get as a subquery.
        if ($this->writer instanceof InnerSqlWriter) {
            $this->writer->innerWriteSql($sb);
        } else {
            $this->writer->writeSql($sb);
        }

        $sql = $sb->getSql();
        $args = $sb->getArgs();

        foreach ($sb->getNamedArgs() as $name => $argIndices) {
            if (!array_key_exists($name, $this->namedArgs)) {
                throw new QueryBuilderException(sprintf('missing named argument "%s"', $name));
            }
            // A name can occupy several placeholders (MySQL '?' is not reusable).
            foreach ($argIndices as $argIdx) {
                $args[$argIdx - 1] = $this->namedArgs[$name];
            }
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
        return new self($this->writer, $args, $this->validating);
    }

    /**
     * Disable validation (e.g. of identifiers) while building.
     */
    public function withoutValidation(): self
    {
        return new self($this->writer, $this->namedArgs, false);
    }
}
