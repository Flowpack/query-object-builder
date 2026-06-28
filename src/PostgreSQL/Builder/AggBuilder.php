<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * Builds an aggregate function call, e.g. `json_agg(expr ORDER BY ...)`,
 * `count(DISTINCT expr)` or `string_agg(expr, ',') WITHIN GROUP (ORDER BY ...)`.
 */
class AggBuilder extends ExpBase
{
    /**
     * @param list<Exp> $exps
     * @param list<OrderByClause> $orderBys
     * @param list<Exp> $filterConjunction
     */
    public function __construct(
        protected readonly string $name,
        protected readonly array $exps,
        protected readonly bool $distinct = false,
        protected readonly array $orderBys = [],
        protected readonly array $filterConjunction = [],
        protected readonly bool $withinGroupOrderBy = false,
    ) {
    }

    public function distinct(): self
    {
        return new self($this->name, $this->exps, true, $this->orderBys, $this->filterConjunction, $this->withinGroupOrderBy);
    }

    /**
     * Add an ORDER BY clause to the aggregate (refine via {@see OrderByAggBuilder}).
     * With {@see withinGroup()} it is written inside WITHIN GROUP instead.
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
    public function filter(Exp $cond): self
    {
        return new self($this->name, $this->exps, $this->distinct, $this->orderBys, [...$this->filterConjunction, $cond], $this->withinGroupOrderBy);
    }

    /**
     * Write the order by clause after the aggregate as WITHIN GROUP (ORDER BY ...).
     */
    public function withinGroup(): self
    {
        return new self($this->name, $this->exps, $this->distinct, $this->orderBys, $this->filterConjunction, true);
    }

    /**
     * Use this aggregate as a window function. Pass an existing window name to
     * reference a window from the query's `WINDOW` clause, or omit it and refine
     * the window inline via {@see WindowFuncCallBuilder::partitionBy()} /
     * {@see WindowFuncCallBuilder::orderBy()}.
     */
    public function over(string $existingWindowName = ''): WindowFuncCallBuilder
    {
        return new WindowFuncCallBuilder($this, new WindowDefinition($existingWindowName));
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
