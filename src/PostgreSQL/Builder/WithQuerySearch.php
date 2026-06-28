<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * The SEARCH clause of a recursive WITH query:
 * `SEARCH { BREADTH | DEPTH } FIRST BY column [, ...] SET search_seq_col`.
 *
 * @internal
 */
final class WithQuerySearch
{
    /**
     * @param list<Exp> $byColumnNames
     */
    public function __construct(
        public readonly WithSearchType $searchType,
        public readonly array $byColumnNames,
        public readonly string $setColumnName,
    ) {
    }
}
