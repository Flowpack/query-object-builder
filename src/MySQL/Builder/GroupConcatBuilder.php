<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * Builds a `GROUP_CONCAT(...)` aggregate:
 * `GROUP_CONCAT([DISTINCT] expr [ORDER BY ...] [SEPARATOR str])`.
 */
class GroupConcatBuilder extends ExpBase
{
    /**
     * @param list<Exp> $exps
     * @param list<OrderByClause> $orderBys
     */
    public function __construct(
        protected readonly array $exps,
        protected readonly bool $distinct = false,
        protected readonly array $orderBys = [],
        protected readonly ?string $separator = null,
    ) {
    }

    public function distinct(): self
    {
        return new self($this->exps, true, $this->orderBys, $this->separator);
    }

    /**
     * Add an ORDER BY expression (refine via {@see OrderByGroupConcatBuilder}).
     */
    public function orderBy(Exp $exp): OrderByGroupConcatBuilder
    {
        return new OrderByGroupConcatBuilder($this->exps, $this->distinct, [...$this->orderBys, new OrderByClause($exp)], $this->separator);
    }

    /**
     * Set the separator string placed between concatenated values (defaults to `,`).
     */
    public function separator(string $separator): self
    {
        return new self($this->exps, $this->distinct, $this->orderBys, $separator);
    }

    public function writeSql(SqlBuilder $sb): void
    {
        $sb->writeString('GROUP_CONCAT(' . ($this->distinct ? 'DISTINCT ' : ''));
        foreach ($this->exps as $i => $exp) {
            if ($i > 0) {
                $sb->writeString(',');
            }
            $exp->writeSql($sb);
        }

        if ($this->orderBys !== []) {
            $sb->writeString(' ORDER BY ');
            foreach ($this->orderBys as $i => $clause) {
                if ($i > 0) {
                    $sb->writeString(',');
                }
                $clause->writeSql($sb);
            }
        }

        if ($this->separator !== null) {
            $sb->writeString(' SEPARATOR ');
            (new StringLiteral($this->separator))->writeSql($sb);
        }

        $sb->writeString(')');
    }
}
