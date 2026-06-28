<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * A boolean literal expression (`true` / `false`).
 */
final class BoolLiteral implements Exp
{
    public function __construct(
        private readonly bool $value,
    ) {
    }

    public function writeSql(SqlBuilder $sb): void
    {
        $sb->writeString($this->value ? 'true' : 'false');
    }
}
