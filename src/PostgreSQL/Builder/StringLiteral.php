<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * A string literal expression, quoted as an SQL string (e.g. `'foo'`).
 *
 * Port of the Go `builder.expStr` / `builder.String`.
 */
final class StringLiteral implements Exp
{
    public function __construct(
        private readonly string $value,
    ) {
    }

    public function writeSql(SqlBuilder $sb): void
    {
        $sb->writeString(Literals::quoteLiteral($this->value));
    }
}
