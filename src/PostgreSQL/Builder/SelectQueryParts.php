<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * Holds the parts of a single SELECT query.
 *
 * This is the mutable state carried by the immutable {@see SelectBuilder} family.
 * The builders clone this object before modifying it, so PHP's copy-on-write
 * array semantics give each derived builder its own copy.
 *
 * Note: when modifying an element that is itself an object (e.g. a
 * {@see FromItem} or {@see OutputExpr}), that element must be cloned as well
 * before mutating it, otherwise the change would leak into the original builder.
 *
 * Only the parts needed so far are present; further clauses (WHERE, GROUP BY,
 * ORDER BY, ...) will be added here as the builder grows.
 */
final class SelectQueryParts
{
    /**
     * @param list<OutputExpr> $selectList
     * @param list<FromItem> $from
     */
    public function __construct(
        public array $selectList = [],
        public array $from = [],
    ) {
    }

    public function isEmpty(): bool
    {
        return $this->selectList === [] && $this->from === [];
    }
}
