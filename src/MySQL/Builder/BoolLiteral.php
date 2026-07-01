<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * A boolean literal expression. MySQL/MariaDB accept `TRUE` / `FALSE` as
 * aliases for `1` / `0`.
 */
final class BoolLiteral implements Exp
{
    public function __construct(
        private readonly bool $value,
    ) {
    }

    public function writeSql(SqlBuilder $sb): void
    {
        $sb->writeString($this->value ? 'TRUE' : 'FALSE');
    }
}
