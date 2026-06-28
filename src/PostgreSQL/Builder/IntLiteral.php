<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * An integer literal expression (e.g. `10`).
 */
final class IntLiteral implements Exp
{
    public function __construct(
        private readonly int $value,
    ) {
    }

    public function writeSql(SqlBuilder $sb): void
    {
        $sb->writeString((string) $this->value);
    }
}
