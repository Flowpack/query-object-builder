<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * A finished CASE expression, e.g. `CASE WHEN a = 1 THEN 'one' ELSE 'other' END`.
 *
 * Produced by {@see CaseBuilder::end()}.
 */
final class CaseExp extends ExpBase
{
    /**
     * @param list<CaseCondition> $conditions
     */
    public function __construct(
        private readonly ?Exp $expression,
        private readonly array $conditions,
        private readonly ?Exp $elseResult,
    ) {
    }

    public function writeSql(SqlBuilder $sb): void
    {
        $sb->writeString('CASE');
        if ($this->expression !== null) {
            $sb->writeString(' ');
            $this->expression->writeSql($sb);
        }

        if ($sb->isValidating() && $this->conditions === []) {
            $sb->addError(new QueryBuilderException('case: no conditions given'));
        }

        foreach ($this->conditions as $condition) {
            $sb->writeString(' WHEN ');
            $condition->condition->writeSql($sb);
            $sb->writeString(' THEN ');
            $condition->result?->writeSql($sb);
        }

        if ($this->elseResult !== null) {
            $sb->writeString(' ELSE ');
            $this->elseResult->writeSql($sb);
        }

        $sb->writeString(' END');
    }
}
