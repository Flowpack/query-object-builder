<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * An interval constant, e.g. `INTERVAL '5 hours'`.
 */
final class IntervalExp implements Exp
{
    public function __construct(
        private readonly string $spec,
    ) {
    }

    public function writeSql(SqlBuilder $sb): void
    {
        $sb->writeString('INTERVAL ' . Literals::quoteLiteral($this->spec));
    }
}
