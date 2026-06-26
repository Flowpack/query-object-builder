<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * An InnerSqlWriter can write itself without surrounding parentheses.
 *
 * A SELECT used as a subquery expression is wrapped in parentheses by
 * {@see SqlWriter::writeSql()}, but when it is the top-level query it must be
 * written without them. {@see QueryBuilder} uses this interface to write the
 * outermost query via {@see self::innerWriteSql()}.
 */
interface InnerSqlWriter extends SqlWriter
{
    public function innerWriteSql(SqlBuilder $sb): void;
}
