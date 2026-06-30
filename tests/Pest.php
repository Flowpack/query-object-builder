<?php

declare(strict_types=1);

use Flowpack\QueryObjectBuilder\MySQL\Builder\QueryBuilder as MySQLQueryBuilder;
use Flowpack\QueryObjectBuilder\MySQL\Builder\SqlWriter as MySQLSqlWriter;
use Flowpack\QueryObjectBuilder\PostgreSQL\Builder\QueryBuilder as PostgreSQLQueryBuilder;
use Flowpack\QueryObjectBuilder\PostgreSQL\Builder\SqlWriter as PostgreSQLSqlWriter;
use PHPUnit\Framework\Assert;

// Expect the query under test to render to the given SQL — ignoring insignificant
// whitespace — and to bind exactly the given positional arguments. Works for any
// dialect: the matching QueryBuilder is chosen from the writer's type.
expect()->extend('toRenderSql', function (string $expectedSql, ?array $args = null): void {
    [$sql, $boundArgs] = renderQuery($this->value);

    Assert::assertSame(normalizeSql($expectedSql), normalizeSql($sql));
    Assert::assertSame($args ?? [], $boundArgs);
});

/**
 * Build the query under test through the QueryBuilder matching its dialect.
 *
 * @return array{0: string, 1: array<int, mixed>}
 */
function renderQuery(mixed $value): array
{
    if ($value instanceof MySQLSqlWriter) {
        return MySQLQueryBuilder::build($value)->toSql();
    }

    return PostgreSQLQueryBuilder::build(asPostgreSQLSqlWriter($value))->toSql();
}

/**
 * Narrow the (statically untyped) expectation value to a PostgreSQL SqlWriter.
 */
function asPostgreSQLSqlWriter(mixed $value): PostgreSQLSqlWriter
{
    if (!$value instanceof PostgreSQLSqlWriter) {
        throw new InvalidArgumentException('toRenderSql() expects a ' . PostgreSQLSqlWriter::class . ' or ' . MySQLSqlWriter::class . ' value.');
    }

    return $value;
}

/**
 * Collapse insignificant whitespace while preserving the minimal separators
 * between tokens, so expected SQL can be written readably in a nowdoc.
 */
function normalizeSql(string $sql): string
{
    $sql = str_replace("\n", ' ', $sql);
    $sql = preg_replace('/(\s|\(|,)\s+/', '$1', $sql) ?? $sql;
    $sql = str_replace(' )', ')', $sql);

    return trim($sql);
}
