<?php

declare(strict_types=1);

use Flowpack\QueryObjectBuilder\MySQL\Builder\QueryBuilder as MySQLQueryBuilder;
use Flowpack\QueryObjectBuilder\MySQL\Builder\QueryBuilderException;
use Flowpack\QueryObjectBuilder\MySQL\Builder\SqlWriter as MySQLSqlWriter;
use Flowpack\QueryObjectBuilder\MySQL\Builder\Target;
use Flowpack\QueryObjectBuilder\PostgreSQL\Builder\QueryBuilder as PostgreSQLQueryBuilder;
use Flowpack\QueryObjectBuilder\PostgreSQL\Builder\SqlWriter as PostgreSQLSqlWriter;
use PHPUnit\Framework\Assert;

// Expect the query under test to render to the given SQL — ignoring insignificant
// whitespace — and to bind exactly the given positional arguments. Works for any
// dialect: the matching QueryBuilder is chosen from the writer's type. Pass a
// MySQL-family $target to also validate the query against that engine while
// rendering (rendering itself never depends on it).
expect()->extend('toRenderSql', function (string $expectedSql, ?array $args = null, ?Target $target = null): void {
    [$sql, $boundArgs] = renderQuery($this->value, $target);

    Assert::assertSame(normalizeSql($expectedSql), normalizeSql($sql));
    Assert::assertSame($args ?? [], $boundArgs);
});

// Expect building the query under test against the given MySQL-family target to fail
// validation with a QueryBuilderException whose message contains $expectedMessage.
expect()->extend('toFailValidationFor', function (Target $target, string $expectedMessage): void {
    $writer = asMySQLSqlWriter($this->value);

    try {
        MySQLQueryBuilder::build($writer)->withValidateTarget($target)->toSql();
        Assert::fail('Expected a QueryBuilderException for the ' . $target->describe() . ' target, but none was thrown.');
    } catch (QueryBuilderException $e) {
        Assert::assertStringContainsString($expectedMessage, $e->getMessage());
    }
});

/**
 * Build the query under test through the QueryBuilder matching its dialect,
 * optionally validating against a MySQL-family target.
 *
 * @return array{0: string, 1: array<int, mixed>}
 */
function renderQuery(mixed $value, ?Target $target = null): array
{
    if ($value instanceof MySQLSqlWriter) {
        $builder = MySQLQueryBuilder::build($value);
        if ($target !== null) {
            $builder = $builder->withValidateTarget($target);
        }

        return $builder->toSql();
    }

    return PostgreSQLQueryBuilder::build(asPostgreSQLSqlWriter($value))->toSql();
}

/**
 * Narrow the (statically untyped) expectation value to a MySQL SqlWriter.
 */
function asMySQLSqlWriter(mixed $value): MySQLSqlWriter
{
    if (!$value instanceof MySQLSqlWriter) {
        throw new InvalidArgumentException('Expected a ' . MySQLSqlWriter::class . ' value.');
    }

    return $value;
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
