<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * The accumulated parts of a single SELECT query.
 *
 * An immutable value object: it is never mutated, only replaced. Derived copies
 * are assembled exclusively by {@see SelectBuilder::derive()}.
 *
 * @internal
 */
final class SelectQueryParts
{
    /**
     * @param list<OutputExpr> $selectList
     * @param list<FromItem> $from
     * @param list<Exp> $whereConjunction conditions joined with AND
     * @param list<GroupingElement> $groupBys
     * @param list<OrderByClause> $orderBys
     */
    public function __construct(
        public readonly ?JsonBuildObjectBuilder $selectJson = null,
        public readonly string $selectJsonAlias = '',
        public readonly array $selectList = [],
        public readonly array $from = [],
        public readonly array $whereConjunction = [],
        public readonly array $groupBys = [],
        public readonly array $orderBys = [],
        public readonly ?Exp $limit = null,
        public readonly ?Exp $offset = null,
    ) {
    }

    public function isEmpty(): bool
    {
        return $this->selectJson === null && $this->selectList === [] && $this->from === []
            && $this->whereConjunction === [] && $this->groupBys === [] && $this->orderBys === []
            && $this->limit === null && $this->offset === null;
    }
}
