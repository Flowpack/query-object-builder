<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * A WITH builder whose latest query name has been started but its body is not
 * yet supplied. Provide the body via {@see as()} (or the materialized variants),
 * optionally naming the columns first via {@see columnNames()}.
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
        $withQueries[$lastIdx] = new WithQueryItem(
            $item->recursive,
            $item->queryName,
            array_values($names),
            $item->materialized,
            $item->query,
            $item->search,
        );

        return new self($withQueries);
    }

    /**
     * Supply the body for the currently started WITH query.
     */
    public function as(WithQuery $query): WithBuilder
    {
        return $this->withBody($query, null);
    }

    public function asMaterialized(WithQuery $query): WithBuilder
    {
        return $this->withBody($query, true);
    }

    public function asNotMaterialized(WithQuery $query): WithBuilder
    {
        return $this->withBody($query, false);
    }

    private function withBody(WithQuery $query, ?bool $materialized): WithBuilder
    {
        $withQueries = $this->withQueries;
        $lastIdx = array_key_last($withQueries);
        assert($lastIdx !== null);

        $item = $withQueries[$lastIdx];
        $withQueries[$lastIdx] = new WithQueryItem(
            $item->recursive,
            $item->queryName,
            $item->columnNames,
            $materialized,
            $query,
            $item->search,
        );

        return new WithBuilder($withQueries);
    }
}
