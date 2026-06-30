<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * A single entry of a WITH clause: `name [(columns)] AS (query)`.
 *
 * @internal
 */
final class WithQueryItem
{
    /**
     * @param list<string> $columnNames
     */
    public function __construct(
        public readonly bool $recursive,
        public readonly string $queryName,
        public readonly array $columnNames = [],
        public readonly ?WithQuery $query = null,
    ) {
    }

    public function writeSql(SqlBuilder $sb): void
    {
        $s = $this->queryName;
        if ($this->columnNames !== []) {
            $s .= '(' . implode(',', $this->columnNames) . ')';
        }
        $s .= ' AS ';
        $sb->writeString($s);

        // The body renders its own surrounding parentheses.
        $this->query?->writeSql($sb);
    }
}
