<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * A writer that can also render itself without the surrounding parentheses it
 * would get as a subquery. {@see QueryBuilder} uses this to write the outermost
 * query bare while a nested {@see SelectBuilder} still parenthesizes itself.
 *
 * @internal
 */
interface InnerSqlWriter extends SqlWriter
{
    public function innerWriteSql(SqlBuilder $sb): void;
}
