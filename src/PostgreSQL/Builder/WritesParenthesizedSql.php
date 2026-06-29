<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * Renders a statement wrapped in parentheses (its subquery / WITH-body form),
 * delegating to {@see InnerSqlWriter::innerWriteSql()} for the bare statement.
 * Shared by the SELECT and the INSERT / UPDATE / DELETE builders.
 *
 * @internal
 * @phpstan-require-implements InnerSqlWriter
 */
trait WritesParenthesizedSql
{
    /**
     * @internal
     */
    public function writeSql(SqlBuilder $sb): void
    {
        $sb->writeString('(');
        $this->innerWriteSql($sb);
        $sb->writeString(')');
    }
}
