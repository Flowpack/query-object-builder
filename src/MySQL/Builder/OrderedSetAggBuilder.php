<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * Builds an ordered-set aggregate — `PERCENTILE_CONT`/`PERCENTILE_DISC` — of the
 * form `NAME(fraction) WITHIN GROUP (ORDER BY ...)`, optionally used as a window
 * function via {@see over()}. Available on MariaDB; it marks itself while rendering
 * so validating against a {@see Target} reports it on MySQL.
 *
 * Call {@see withinGroup()} then {@see orderBy()} to place the order expression in
 * the `WITHIN GROUP` clause.
 */
class OrderedSetAggBuilder extends ExpBase
{
    /**
     * @param list<Exp> $args
     * @param list<OrderByClause> $orderBys
     */
    public function __construct(
        protected readonly string $name,
        protected readonly array $args,
        protected readonly Requirement $requires,
        protected readonly array $orderBys = [],
        protected readonly bool $withinGroupOrderBy = false,
    ) {
    }

    /**
     * Render the following {@see orderBy()} inside `WITHIN GROUP (ORDER BY ...)`.
     */
    public function withinGroup(): self
    {
        return new self($this->name, $this->args, $this->requires, $this->orderBys, true);
    }

    /**
     * Add the ordering expression (refine its direction via {@see OrderByOrderedSetAggBuilder}).
     */
    public function orderBy(Exp $exp): OrderByOrderedSetAggBuilder
    {
        return new OrderByOrderedSetAggBuilder(
            $this->name,
            $this->args,
            $this->requires,
            [...$this->orderBys, new OrderByClause($exp)],
            $this->withinGroupOrderBy,
        );
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
        $sb->requireAnyDialect($this->name, $this->requires);

        $sb->writeString($this->name . '(');
        foreach ($this->args as $i => $arg) {
            if ($i > 0) {
                $sb->writeString(',');
            }
            $arg->writeSql($sb);
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
