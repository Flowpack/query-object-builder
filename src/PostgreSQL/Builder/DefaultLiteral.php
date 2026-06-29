<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * The SQL `DEFAULT` keyword, usable as a value in INSERT / UPDATE.
 */
final class DefaultLiteral implements Exp
{
    public function writeSql(SqlBuilder $sb): void
    {
        $sb->writeString('DEFAULT');
    }
}
