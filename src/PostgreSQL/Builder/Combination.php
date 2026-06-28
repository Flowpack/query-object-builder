<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * A previous SELECT combined with the following one via UNION, INTERSECT or
 * EXCEPT. The combined query is either built further on the select builder or
 * supplied explicitly via {@see CombinationBuilder::query()}.
 *
 * @internal
 */
final class Combination
{
    public function __construct(
        public readonly SelectQueryParts $parts,
        public readonly CombinationType $type,
        public readonly bool $all = false,
        public readonly ?SelectBuilder $query = null,
    ) {
    }
}
