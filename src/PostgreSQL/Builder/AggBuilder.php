<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * Builds an aggregate function call, e.g. `json_agg(expr ORDER BY ...)`,
 * `count(DISTINCT expr)` or `string_agg(expr, ',') WITHIN GROUP (ORDER BY ...)`.
 *
 * Immutable: every method returns a new builder.
 *
 * Port of the Go `builder.AggBuilder`.
 */
class AggBuilder extends ExpBase
{
    /**
     * @param list<Exp> $exps
     * @param list<OrderByClause> $orderBys
     * @param list<Exp> $filterConjunction
     */
    public function __construct(
        protected string $name,
        protected array $exps,
        protected bool $distinct = false,
        protected array $orderBys = [],
        protected array $filterConjunction = [],
        protected bool $withinGroupOrderBy = false,
    ) {
    }

    public function distinct(): static
    {
        $b = clone $this;
        $b->distinct = true;

        return $b;
    }

    /**
     * Add an ORDER BY clause to the aggregate (refine via {@see OrderByAggBuilder}).
     * If {@see withinGroup()} is used, this order by is written inside WITHIN GROUP.
     */
    public function orderBy(Exp $exp): OrderByAggBuilder
    {
        return new OrderByAggBuilder(
            $this->name,
            $this->exps,
            $this->distinct,
            [...$this->orderBys, new OrderByClause($exp)],
            $this->filterConjunction,
            $this->withinGroupOrderBy,
        );
    }

    /**
     * Add a FILTER (WHERE ...) clause. Multiple calls are joined with AND.
     */
    public function filter(Exp $cond): static
    {
        $b = clone $this;
        $b->filterConjunction = [...$this->filterConjunction, $cond];

        return $b;
    }

    /**
     * Write the order by clause after the aggregate as WITHIN GROUP (ORDER BY ...).
     */
    public function withinGroup(): static
    {
        $b = clone $this;
        $b->withinGroupOrderBy = true;

        return $b;
    }

    public function writeSql(SqlBuilder $sb): void
    {
        $sb->writeString($this->name . '(' . ($this->distinct ? 'DISTINCT ' : ''));
        foreach ($this->exps as $i => $exp) {
            if ($i > 0) {
                $sb->writeString(',');
            }
            $exp->writeSql($sb);
        }
        if (!$this->withinGroupOrderBy && $this->orderBys !== []) {
            $sb->writeString(' ORDER BY ');
            $this->writeOrderBys($sb);
        }
        $sb->writeString(')');

        if ($this->withinGroupOrderBy) {
            $sb->writeString(' WITHIN GROUP (ORDER BY ');
            $this->writeOrderBys($sb);
            $sb->writeString(')');
        }

        if ($this->filterConjunction !== []) {
            $sb->writeString(' FILTER (WHERE ');
            Junction::and(...$this->filterConjunction)->writeSql($sb);
            $sb->writeString(')');
        }
    }

    private function writeOrderBys(SqlBuilder $sb): void
    {
        foreach ($this->orderBys as $i => $clause) {
            if ($i > 0) {
                $sb->writeString(',');
            }
            $clause->writeSql($sb);
        }
    }
}
