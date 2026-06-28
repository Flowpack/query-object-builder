<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * A `ROWS FROM ( ... )` FROM item combining several set-returning functions,
 * optionally with `WITH ORDINALITY`.
 */
final class RowsFromBuilder implements FromLateralExp
{
    /**
     * @param list<FuncBuilder> $fns
     */
    public function __construct(
        private readonly array $fns,
        private readonly bool $withOrdinality = false,
    ) {
    }

    public function withOrdinality(): self
    {
        return new self($this->fns, true);
    }

    public function writeSql(SqlBuilder $sb): void
    {
        $sb->writeString('ROWS FROM (');
        foreach ($this->fns as $i => $fn) {
            if ($i > 0) {
                $sb->writeString(',');
            }
            $fn->writeSql($sb);
        }

        $s = ')';
        if ($this->withOrdinality) {
            $s .= ' WITH ORDINALITY';
        }
        $sb->writeString($s);
    }
}
