<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * The parenthesized body of a window definition, shared by a window function
 * call's `OVER (...)` and a named entry of the `WINDOW` clause: an optional
 * existing window to copy from, a `PARTITION BY` list and an `ORDER BY` list.
 *
 * @internal
 */
final class WindowDefinition
{
    /**
     * @param list<Exp> $partitionBy
     * @param list<OrderByClause> $orderBys
     */
    public function __construct(
        public readonly string $existingWindowName = '',
        public readonly array $partitionBy = [],
        public readonly array $orderBys = [],
    ) {
    }

    /**
     * Whether this definition is nothing but a reference to an existing window, so
     * a window function can write the bare `OVER name` form instead of `OVER (...)`.
     */
    public function isExistingNameOnly(): bool
    {
        return $this->existingWindowName !== '' && $this->partitionBy === [] && $this->orderBys === [];
    }

    public function writeSql(SqlBuilder $sb): void
    {
        $s = '(';
        $hasContent = false;
        if ($this->existingWindowName !== '') {
            $s .= $this->existingWindowName;
            $hasContent = true;
        }
        if ($this->partitionBy !== []) {
            $sb->writeString($s . ($hasContent ? ' ' : '') . 'PARTITION BY ');
            $s = '';
            foreach ($this->partitionBy as $i => $exp) {
                if ($i > 0) {
                    $sb->writeString(',');
                }
                $exp->writeSql($sb);
            }
            $hasContent = true;
        }
        if ($this->orderBys !== []) {
            $sb->writeString($s . ($hasContent ? ' ' : '') . 'ORDER BY ');
            $s = '';
            foreach ($this->orderBys as $i => $clause) {
                if ($i > 0) {
                    $sb->writeString(',');
                }
                $clause->writeSql($sb);
            }
        }
        $sb->writeString($s . ')');
    }
}
