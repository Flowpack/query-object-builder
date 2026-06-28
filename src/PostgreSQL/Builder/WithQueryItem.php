<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * A single entry of a WITH clause:
 * `name [(columns)] AS [[NOT] MATERIALIZED] (query) [SEARCH ...]`.
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
        public readonly ?bool $materialized = null,
        public readonly ?WithQuery $query = null,
        public readonly ?WithQuerySearch $search = null,
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

        if ($this->search !== null) {
            $sb->writeString(' SEARCH ' . $this->search->searchType->value . ' FIRST BY ');
            foreach ($this->search->byColumnNames as $i => $exp) {
                if ($i > 0) {
                    $sb->writeString(',');
                }
                $exp->writeSql($sb);
            }
            $sb->writeString(' SET ' . $this->search->setColumnName);
        }
    }
}
