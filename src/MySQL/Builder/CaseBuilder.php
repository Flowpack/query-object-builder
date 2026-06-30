<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * Builds a CASE expression. Add branches with {@see when()} / `then()`, an
 * optional {@see else()}, and finish with {@see end()}.
 */
final class CaseBuilder
{
    /**
     * @param list<CaseCondition> $conditions
     */
    public function __construct(
        private readonly ?Exp $expression = null,
        private readonly array $conditions = [],
        private readonly ?Exp $elseResult = null,
    ) {
    }

    /**
     * Start a `WHEN condition` branch; supply its result via
     * {@see CaseWhenBuilder::then()}.
     */
    public function when(Exp $condition): CaseWhenBuilder
    {
        return new CaseWhenBuilder(
            $this->expression,
            [...$this->conditions, new CaseCondition($condition)],
            $this->elseResult,
        );
    }

    /**
     * Set the `ELSE` result.
     */
    public function else(Exp $result): self
    {
        return new self($this->expression, $this->conditions, $result);
    }

    /**
     * Finish the CASE expression.
     */
    public function end(): CaseExp
    {
        return new CaseExp($this->expression, $this->conditions, $this->elseResult);
    }
}
