<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * A window function expression, `func(...) OVER (...)` or `func(...) OVER name`.
 * Refine the inline window definition via {@see partitionBy()} / {@see orderBy()}
 * and bound the frame via {@see rows()} / {@see range()}.
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
            $this->definition->frame,
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
            $this->definition->frame,
        ));
    }

    /**
     * Bound the frame in `ROWS` units. Pass one bound for the `ROWS start` form or
     * two for `ROWS BETWEEN start AND end`.
     */
    public function rows(FrameBound $start, ?FrameBound $end = null): self
    {
        return $this->withFrame(new WindowFrame(FrameUnits::Rows, $start, $end));
    }

    /**
     * Bound the frame in `RANGE` units. Pass one bound for the `RANGE start` form
     * or two for `RANGE BETWEEN start AND end`.
     */
    public function range(FrameBound $start, ?FrameBound $end = null): self
    {
        return $this->withFrame(new WindowFrame(FrameUnits::Range, $start, $end));
    }

    private function withFrame(WindowFrame $frame): self
    {
        return new self($this->funcCall, new WindowDefinition(
            $this->definition->existingWindowName,
            $this->definition->partitionBy,
            $this->definition->orderBys,
            $frame,
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
