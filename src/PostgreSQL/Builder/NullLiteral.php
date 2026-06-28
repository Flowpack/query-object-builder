<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * The SQL `NULL` literal.
 */
final class NullLiteral implements Exp
{
    public function writeSql(SqlBuilder $sb): void
    {
        $sb->writeString('NULL');
    }
}
