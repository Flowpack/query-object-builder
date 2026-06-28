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
 * Further clauses (HAVING, WINDOW, ...) will be added here as the builder grows.
 */
final class SelectQueryParts
{
    /**
     * @param list<OutputExpr> $selectList
     * @param list<FromItem> $from
     * @param list<Exp> $whereConjunction the WHERE conditions, joined with AND
     * @param list<GroupingElement> $groupBys
     * @param list<OrderByClause> $orderBys
     */
    public function __construct(
        public ?JsonBuildObjectBuilder $selectJson = null,
        public string $selectJsonAlias = '',
        public array $selectList = [],
        public array $from = [],
        public array $whereConjunction = [],
        public array $groupBys = [],
        public array $orderBys = [],
        public ?Exp $limit = null,
        public ?Exp $offset = null,
    ) {
    }

    public function isEmpty(): bool
    {
        return $this->selectJson === null && $this->selectList === [] && $this->from === []
            && $this->whereConjunction === [] && $this->groupBys === [] && $this->orderBys === []
            && $this->limit === null && $this->offset === null;
    }
}
