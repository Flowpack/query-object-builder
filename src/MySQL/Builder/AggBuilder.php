<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * Builds an aggregate function call such as `COUNT(expr)`, `SUM(DISTINCT expr)`
 * or `AVG(expr)`. Call {@see over()} to use it as a window function.
 */
class AggBuilder extends ExpBase
{
    /**
     * @param list<Exp> $exps
     */
    public function __construct(
        protected readonly string $name,
        protected readonly array $exps,
        protected readonly bool $distinct = false,
    ) {
    }

    public function distinct(): self
    {
        return new self($this->name, $this->exps, true);
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
        $sb->writeString(')');
    }
}
