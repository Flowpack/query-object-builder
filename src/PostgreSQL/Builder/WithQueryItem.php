<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * A single entry in a WITH clause: `name [(columns)] AS [[NOT] MATERIALIZED] (query)`.
 *
 * Mutable by design: the WITH builders copy it (via clone) before setting the
 * query / column names / materialization.
 *
 * Port of the Go `builder.withQuery`.
 */
final class WithQueryItem
{
    /**
     * @param list<string> $columnNames
     */
    public function __construct(
        public bool $recursive,
        public string $queryName,
        public array $columnNames = [],
        public ?bool $materialized = null,
        public ?WithQuery $query = null,
    ) {
    }

    public function writeSql(SqlBuilder $sb): void
    {
        $s = $this->queryName;
        if ($this->columnNames !== []) {
            $s .= '(' . implode(',', $this->columnNames) . ')';
        }
        $s .= ' AS ';
        if ($this->materialized !== null) {
            if (!$this->materialized) {
                $s .= 'NOT ';
            }
            $s .= 'MATERIALIZED ';
        }
        $sb->writeString($s);

        $this->query?->writeSql($sb);
    }
}
