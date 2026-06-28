<?php

declare(strict_types=1);

use Flowpack\QueryObjectBuilder\PostgreSQL\Builder\QueryBuilder;
use Flowpack\QueryObjectBuilder\PostgreSQL\Builder\SqlWriter;
use PHPUnit\Framework\Assert;

// Expect the query under test to render to the given SQL — ignoring insignificant
// whitespace — and to bind exactly the given positional arguments.
expect()->extend('toRenderSql', function (string $expectedSql, ?array $args = null): void {
    [$sql, $boundArgs] = QueryBuilder::build(asSqlWriter($this->value))->toSql();

    Assert::assertSame(normalizeSql($expectedSql), normalizeSql($sql));
    Assert::assertSame($args ?? [], $boundArgs);
});

/**
 * Narrow the (statically untyped) expectation value to a SqlWriter.
 */
function asSqlWriter(mixed $value): SqlWriter
{
    if (!$value instanceof SqlWriter) {
        throw new InvalidArgumentException('toRenderSql() expects a ' . SqlWriter::class . ' value.');
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
