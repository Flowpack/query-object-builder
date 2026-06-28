<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * A window function expression, `func(...) OVER (...)` or `func(...) OVER name`.
 * Refine the inline window definition via {@see partitionBy()} / {@see orderBy()}.
 */
class WindowFuncCallBuilder extends ExpBase
{
    public function __construct(
        protected readonly Exp $funcCall,
        protected readonly WindowDefinition $definition,
    ) {
    }

    public function partitionBy(Exp $exp, Exp ...$exps): self
    {
        return new self($this->funcCall, new WindowDefinition(
            $this->definition->existingWindowName,
            [...$this->definition->partitionBy, $exp, ...array_values($exps)],
            $this->definition->orderBys,
        ));
    }

    /**
     * Add an ORDER BY expression to the window (refine via {@see OrderByWindowFuncCallBuilder}).
     */
    public function orderBy(Exp $exp): OrderByWindowFuncCallBuilder
    {
        return new OrderByWindowFuncCallBuilder($this->funcCall, new WindowDefinition(
            $this->definition->existingWindowName,
            $this->definition->partitionBy,
            [...$this->definition->orderBys, new OrderByClause($exp)],
        ));
    }

    public function writeSql(SqlBuilder $sb): void
    {
        $this->funcCall->writeSql($sb);
        $sb->writeString(' OVER ');
        // A bare existing window name is written without parentheses.
        if ($this->definition->isExistingNameOnly()) {
            $sb->writeString($this->definition->existingWindowName);
        } else {
            $this->definition->writeSql($sb);
        }
    }
}
