<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * The WITH builder state after naming the SEARCH ordering columns; finish the
 * clause with {@see set()}, which names the search-sequence column.
 */
final class WithSearchByBuilder
{
    /**
     * @param list<WithQueryItem> $withQueries
     * @param list<Exp> $byColumnNames
     */
    public function __construct(
        private readonly array $withQueries,
        private readonly WithSearchType $searchType,
        private readonly array $byColumnNames,
    ) {
    }

    public function set(string $setColumnName): WithBuilder
    {
        $withQueries = $this->withQueries;
        $lastIdx = array_key_last($withQueries);
        assert($lastIdx !== null);

        $item = $withQueries[$lastIdx];
        $withQueries[$lastIdx] = new WithQueryItem(
            $item->recursive,
            $item->queryName,
            $item->columnNames,
            $item->materialized,
            $item->query,
            new WithQuerySearch($this->searchType, $this->byColumnNames, $setColumnName),
        );

        return new WithBuilder($withQueries);
    }
}
