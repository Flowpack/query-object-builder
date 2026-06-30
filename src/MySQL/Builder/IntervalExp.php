<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * A temporal `INTERVAL expr unit` expression (e.g. `INTERVAL 1 DAY`), used as an
 * operand of date arithmetic and of `DATE_ADD` / `DATE_SUB`.
 *
 * @internal
 */
final class IntervalExp extends ExpBase
{
    public function __construct(
        private readonly Exp $expr,
        private readonly string $unit,
    ) {
    }

    public function writeSql(SqlBuilder $sb): void
    {
        $sb->writeString('INTERVAL ');
        $this->expr->writeSql($sb);
        $sb->writeString(' ' . $this->unit);
    }
}
