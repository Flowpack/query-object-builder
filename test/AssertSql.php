<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\Test;

use Flowpack\QueryObjectBuilder\PostgreSQL\Builder\QueryBuilder;
use Flowpack\QueryObjectBuilder\PostgreSQL\Builder\SqlWriter;

/**
 * Assertion helpers for comparing the SQL produced by a query builder.
 */
trait AssertSql
{
    /**
     * Build the given writer and assert that the generated SQL (ignoring
     * insignificant whitespace) and the bound arguments match the expectation.
     *
     * @param array<int, mixed>|null $expectedArgs
     */
    protected function assertSqlWriterEquals(string $expectedSql, ?array $expectedArgs, SqlWriter $writer): void
    {
        [$sql, $args] = QueryBuilder::build($writer)->toSql();

        $this->assertSqlEquals($expectedSql, $sql);

        if ($expectedArgs === null || $expectedArgs === []) {
            self::assertEmpty($args, 'Expected no bound arguments');
        } else {
            self::assertEquals($expectedArgs, $args);
        }
    }

    /**
     * Assert that two SQL strings are equal, ignoring insignificant whitespace
     * (so the expected SQL can be written for readability), while preserving
     * the minimal whitespace that separates tokens.
     */
    protected function assertSqlEquals(string $expected, string $actual): void
    {
        self::assertSame($this->normalizeSql($expected), $this->normalizeSql($actual));
    }

    private function normalizeSql(string $s): string
    {
        // Replace all newlines with spaces to make the replacement regexp easier.
        $s = str_replace("\n", ' ', $s);
        // Normalize whitespace (collapse multiple spaces, remove space after opening brackets or comma).
        $s = preg_replace('/(\s|\(|,)\s+/', '$1', $s) ?? $s;
        // Remove space before closing brackets, ideally this would be handled by the pattern above.
        $s = str_replace(' )', ')', $s);

        return trim($s);
    }
}
