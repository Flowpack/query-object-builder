<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * A WITH builder whose latest query name has been started but its body is not
 * yet supplied. Provide the body via {@see as()}, optionally naming the columns
 * first via {@see columnNames()}.
 */
final class WithWithBuilder
{
    /**
     * @param list<WithQueryItem> $withQueries
     */
    public function __construct(
        private readonly array $withQueries,
    ) {
    }

    /**
     * Set the column names for the currently started WITH query.
     */
    public function columnNames(string ...$names): self
    {
        $withQueries = $this->withQueries;
        $lastIdx = array_key_last($withQueries);
        assert($lastIdx !== null);

        $item = $withQueries[$lastIdx];
        $withQueries[$lastIdx] = new WithQueryItem($item->recursive, $item->queryName, array_values($names), $item->query);

        return new self($withQueries);
    }

    /**
     * Supply the body for the currently started WITH query.
     */
    public function as(WithQuery $query): WithBuilder
    {
        $withQueries = $this->withQueries;
        $lastIdx = array_key_last($withQueries);
        assert($lastIdx !== null);

        $item = $withQueries[$lastIdx];
        $withQueries[$lastIdx] = new WithQueryItem($item->recursive, $item->queryName, $item->columnNames, $query);

        return new WithBuilder($withQueries);
    }
}
