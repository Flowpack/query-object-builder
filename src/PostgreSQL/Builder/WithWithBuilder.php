<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * The state of a WITH builder right after a query name was started but its body
 * is not yet supplied. Provide the body via {@see as()} (or the materialized
 * variants), optionally naming the columns first via {@see columnNames()}.
 *
 * Port of the Go `builder.WithWithBuilder`.
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

        $item = clone $withQueries[$lastIdx];
        $item->columnNames = array_values($names);
        $withQueries[$lastIdx] = $item;

        return new self($withQueries);
    }

    /**
     * Supply the body for the currently started WITH query.
     */
    public function as(WithQuery $query): WithBuilder
    {
        return $this->asWithMaterialized($query, null);
    }

    public function asMaterialized(WithQuery $query): WithBuilder
    {
        return $this->asWithMaterialized($query, true);
    }

    public function asNotMaterialized(WithQuery $query): WithBuilder
    {
        return $this->asWithMaterialized($query, false);
    }

    private function asWithMaterialized(WithQuery $query, ?bool $materialized): WithBuilder
    {
        $withQueries = $this->withQueries;
        $lastIdx = array_key_last($withQueries);
        assert($lastIdx !== null);

        $item = clone $withQueries[$lastIdx];
        $item->query = $query;
        $item->materialized = $materialized;
        $withQueries[$lastIdx] = $item;

        return new WithBuilder($withQueries);
    }
}
