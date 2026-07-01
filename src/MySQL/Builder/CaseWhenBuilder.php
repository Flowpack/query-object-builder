<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * The CASE builder state right after a {@see CaseBuilder::when()} condition,
 * awaiting its result via {@see then()}.
 */
final class CaseWhenBuilder
{
    /**
     * @param list<CaseCondition> $conditions the last entry has no result yet
     */
    public function __construct(
        private readonly ?Exp $expression,
        private readonly array $conditions,
        private readonly ?Exp $elseResult,
    ) {
    }

    /**
     * Set the result for the preceding WHEN condition.
     */
    public function then(Exp $result): CaseBuilder
    {
        $conditions = $this->conditions;
        $lastIdx = array_key_last($conditions);
        assert($lastIdx !== null);
        $conditions[$lastIdx] = new CaseCondition($conditions[$lastIdx]->condition, $result);

        return new CaseBuilder($this->expression, $conditions, $this->elseResult);
    }
}
