<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * The WITH builder state after starting a SEARCH clause; name the ordering
 * columns via {@see by()}.
 */
final class WithSearchBuilder
{
    /**
     * @param list<WithQueryItem> $withQueries
     */
    public function __construct(
        private readonly array $withQueries,
        private readonly WithSearchType $searchType,
    ) {
    }

    public function by(Exp $columnName, Exp ...$columnNames): WithSearchByBuilder
    {
        return new WithSearchByBuilder($this->withQueries, $this->searchType, array_values([$columnName, ...$columnNames]));
    }
}
