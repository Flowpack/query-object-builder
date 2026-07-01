<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * Renders a statement wrapped in parentheses (its subquery form), delegating to
 * {@see InnerSqlWriter::innerWriteSql()} for the bare statement.
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
