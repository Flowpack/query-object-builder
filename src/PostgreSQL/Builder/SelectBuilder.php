<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * Builds a SELECT query.
 *
 * Two principles run through this family of builders:
 *
 *  - Immutability: every method returns a new builder; the receiver is never
 *    modified. The state lives in an immutable {@see SelectQueryParts}, and a
 *    derived copy is assembled only by {@see derive()}.
 *  - Type-state: methods return a more specific builder type (e.g. {@see
 *    FromSelectBuilder} after {@see from()}) so that context-dependent methods
 *    like `as()`, `using()` or `on()` are only available — and only act on the
 *    relevant element — where they make sense.
 *
 * The specific builders extend this base class, so they keep access to all the
 * generic clause methods (`select()`, `from()`, `join()`, ...).
 */
class SelectBuilder implements InnerSqlWriter, WithQuery, Exp, FromLateralExp, SelectOrExpressions
{
    /**
     * @param list<WithQueryItem> $withQueries the leading WITH clause, if any
     * @param list<Combination> $combinations previous selects combined via UNION / INTERSECT / EXCEPT
     */
    public function __construct(
        protected readonly SelectQueryParts $parts = new SelectQueryParts(),
        protected readonly array $withQueries = [],
        protected readonly array $combinations = [],
    ) {
    }

    /**
     * Apply a function to the JSON selection (an empty json_build_object if none
     * is set yet). The JSON selection is always written as the first select element.
     *
     * @param callable(JsonBuildObjectBuilder): JsonBuildObjectBuilder $apply
     */
    public function applySelectJson(callable $apply): SelectJsonSelectBuilder
    {
        return $this->derive(
            SelectJsonSelectBuilder::class,
            selectJson: $apply($this->parts->selectJson ?? new JsonBuildObjectBuilder(false)),
        );
    }

    /**
     * Add the given expressions to the select list.
     */
    public function select(Exp ...$exps): SelectSelectBuilder
    {
        $selectList = $this->parts->selectList;
        foreach ($exps as $exp) {
            $selectList[] = new OutputExpr($exp);
        }

        return $this->derive(SelectSelectBuilder::class, selectList: $selectList);
    }

    /**
     * Add a table / function / subquery to the FROM clause.
     */
    public function from(FromExp $from): FromSelectBuilder
    {
        return $this->derive(FromSelectBuilder::class, from: [...$this->parts->from, new FromItem($from)]);
    }

    /**
     * Add a `LATERAL` table / function / subquery to the FROM clause.
     */
    public function fromLateral(FromLateralExp $from): FromSelectBuilder
    {
        return $this->derive(FromSelectBuilder::class, from: [...$this->parts->from, new FromItem($from, lateral: true)]);
    }

    /**
     * Add an `ONLY` table to the FROM clause (no descendant tables).
     */
    public function fromOnly(FromExp $from): FromSelectBuilder
    {
        return $this->derive(FromSelectBuilder::class, from: [...$this->parts->from, new FromItem($from, only: true)]);
    }

    public function join(FromExp $from): JoinSelectBuilder
    {
        return $this->addJoin(JoinType::Inner, $from, false);
    }

    public function joinLateral(FromExp $from): JoinSelectBuilder
    {
        return $this->addJoin(JoinType::Inner, $from, true);
    }

    public function leftJoin(FromExp $from): JoinSelectBuilder
    {
        return $this->addJoin(JoinType::Left, $from, false);
    }

    public function leftJoinLateral(FromExp $from): JoinSelectBuilder
    {
        return $this->addJoin(JoinType::Left, $from, true);
    }

    public function rightJoin(FromExp $from): JoinSelectBuilder
    {
        return $this->addJoin(JoinType::Right, $from, false);
    }

    public function fullJoin(FromExp $from): JoinSelectBuilder
    {
        return $this->addJoin(JoinType::Full, $from, false);
    }

    public function crossJoin(FromExp $from): JoinSelectBuilder
    {
        return $this->addJoin(JoinType::Cross, $from, false);
    }

    public function crossJoinLateral(FromExp $from): JoinSelectBuilder
    {
        return $this->addJoin(JoinType::Cross, $from, true);
    }

    private function addJoin(JoinType $joinType, FromExp $from, bool $lateral): JoinSelectBuilder
    {
        return $this->derive(
            JoinSelectBuilder::class,
            from: [...$this->parts->from, new FromItem(new Join($joinType, $lateral, $from))],
        );
    }

    /**
     * Add a WHERE condition. Multiple calls are joined with AND.
     */
    public function where(Exp $cond): SelectBuilder
    {
        return $this->derive(SelectBuilder::class, whereConjunction: [...$this->parts->whereConjunction, $cond]);
    }

    /**
     * Add a grouping element for the given expressions to the GROUP BY clause.
     * With no expressions, the special grouping elements on {@see GroupBySelectBuilder}
     * (`empty()`, `rollup()`, `cube()`, `groupingSets()`, `distinct()`) become available.
     */
    public function groupBy(Exp ...$exps): GroupBySelectBuilder
    {
        if ($exps === []) {
            return $this->derive(GroupBySelectBuilder::class);
        }

        return $this->derive(GroupBySelectBuilder::class, groupBys: [...$this->parts->groupBys, new GroupingElement([array_values($exps)])]);
    }

    /**
     * Add a HAVING condition. Multiple calls are joined with AND.
     */
    public function having(Exp $cond): SelectBuilder
    {
        return $this->derive(SelectBuilder::class, havingConjunction: [...$this->parts->havingConjunction, $cond]);
    }

    /**
     * Add an ORDER BY expression (refine it via {@see OrderBySelectBuilder}).
     */
    public function orderBy(Exp $exp): OrderBySelectBuilder
    {
        return $this->derive(OrderBySelectBuilder::class, orderBys: [...$this->parts->orderBys, new OrderByClause($exp)]);
    }

    public function limit(Exp $exp): SelectBuilder
    {
        return $this->derive(SelectBuilder::class, limit: $exp);
    }

    public function offset(Exp $exp): SelectBuilder
    {
        return $this->derive(SelectBuilder::class, offset: $exp);
    }

    /**
     * Combine this select with the following one using UNION. Refine with
     * {@see CombinationBuilder::all()} or supply the query via
     * {@see CombinationBuilder::query()}.
     */
    public function union(): CombinationBuilder
    {
        return $this->addCombination(CombinationType::Union);
    }

    public function intersect(): CombinationBuilder
    {
        return $this->addCombination(CombinationType::Intersect);
    }

    public function except(): CombinationBuilder
    {
        return $this->addCombination(CombinationType::Except);
    }

    private function addCombination(CombinationType $type): CombinationBuilder
    {
        // Archive the current parts as a combination and start a fresh select.
        return $this->derive(
            CombinationBuilder::class,
            parts: new SelectQueryParts(),
            combinations: [...$this->combinations, new Combination($this->parts, $type)],
        );
    }

    public function forUpdate(): ForSelectBuilder
    {
        return $this->derive(ForSelectBuilder::class, lockingClause: new LockingClause('UPDATE'));
    }

    public function forNoKeyUpdate(): ForSelectBuilder
    {
        return $this->derive(ForSelectBuilder::class, lockingClause: new LockingClause('NO KEY UPDATE'));
    }

    public function forShare(): ForSelectBuilder
    {
        return $this->derive(ForSelectBuilder::class, lockingClause: new LockingClause('SHARE'));
    }

    public function forKeyShare(): ForSelectBuilder
    {
        return $this->derive(ForSelectBuilder::class, lockingClause: new LockingClause('KEY SHARE'));
    }

    /**
     * Append the given WITH queries to this select's WITH clause.
     */
    public function appendWith(WithBuilder $with): SelectBuilder
    {
        return $this->derive(SelectBuilder::class, withQueries: [...$this->withQueries, ...$with->withQueryItems()]);
    }

    /**
     * Whether this builder carries no content yet: no WITH queries, no
     * combinations and empty select parts. Useful for conditional query building.
     */
    public function isEmpty(): bool
    {
        return $this->withQueries === [] && $this->combinations === [] && $this->parts->isEmpty();
    }

    /**
     * Assemble a new builder of the given type from the current state with the
     * given fields replaced; a null argument keeps the current value. This is the
     * single place where a derived {@see SelectQueryParts} and the type-state
     * transition are produced.
     *
     * Pass `$parts` to replace the current parts wholesale (used when starting a
     * combination, which archives the current parts and resets to an empty
     * select); otherwise the individual field arguments patch the current parts.
     *
     * @template T of SelectBuilder
     * @param class-string<T> $class
     * @param list<Exp>|null $distinctOn
     * @param list<OutputExpr>|null $selectList
     * @param list<FromItem>|null $from
     * @param list<Exp>|null $whereConjunction
     * @param list<GroupingElement>|null $groupBys
     * @param list<Exp>|null $havingConjunction
     * @param list<OrderByClause>|null $orderBys
     * @param list<WithQueryItem>|null $withQueries
     * @param list<Combination>|null $combinations
     * @return T
     */
    protected function derive(
        string $class,
        ?SelectQueryParts $parts = null,
        ?bool $distinct = null,
        ?array $distinctOn = null,
        ?JsonBuildObjectBuilder $selectJson = null,
        ?string $selectJsonAlias = null,
        ?array $selectList = null,
        ?array $from = null,
        ?array $whereConjunction = null,
        ?bool $groupByDistinct = null,
        ?array $groupBys = null,
        ?array $havingConjunction = null,
        ?array $orderBys = null,
        ?Exp $limit = null,
        ?Exp $offset = null,
        ?LockingClause $lockingClause = null,
        ?array $withQueries = null,
        ?array $combinations = null,
    ): SelectBuilder {
        $parts ??= new SelectQueryParts(
            distinct: $distinct ?? $this->parts->distinct,
            distinctOn: $distinctOn ?? $this->parts->distinctOn,
            selectJson: $selectJson ?? $this->parts->selectJson,
            selectJsonAlias: $selectJsonAlias ?? $this->parts->selectJsonAlias,
            selectList: $selectList ?? $this->parts->selectList,
            from: $from ?? $this->parts->from,
            whereConjunction: $whereConjunction ?? $this->parts->whereConjunction,
            groupByDistinct: $groupByDistinct ?? $this->parts->groupByDistinct,
            groupBys: $groupBys ?? $this->parts->groupBys,
            havingConjunction: $havingConjunction ?? $this->parts->havingConjunction,
            orderBys: $orderBys ?? $this->parts->orderBys,
            limit: $limit ?? $this->parts->limit,
            offset: $offset ?? $this->parts->offset,
            lockingClause: $lockingClause ?? $this->parts->lockingClause,
        );

        return new $class($parts, $withQueries ?? $this->withQueries, $combinations ?? $this->combinations);
    }

    /**
     * Write the select as a subquery expression, wrapped in parentheses.
     *
     * @internal
     */
    public function writeSql(SqlBuilder $sb): void
    {
        $sb->writeString('(');
        $this->innerWriteSql($sb);
        $sb->writeString(')');
    }

    /**
     * Write the select without the surrounding parentheses (the top-level query).
     *
     * @internal
     */
    public function innerWriteSql(SqlBuilder $sb): void
    {
        if ($this->withQueries !== []) {
            $this->writeWithQueries($sb);
        }

        // Previous selects combined via UNION / INTERSECT / EXCEPT come first.
        foreach ($this->combinations as $c) {
            $this->writeSelectParts($sb, $c->parts);
            $s = ' ' . $c->type->value;
            if ($c->all) {
                $s .= ' ALL';
            }
            $sb->writeString($s . ' ');
            $c->query?->writeSql($sb);
        }

        if (!$this->parts->isEmpty()) {
            $this->writeSelectParts($sb, $this->parts);
        }

        if ($this->parts->orderBys !== []) {
            $sb->writeString(' ORDER BY ');
            foreach ($this->parts->orderBys as $i => $clause) {
                if ($i > 0) {
                    $sb->writeString(',');
                }
                $clause->writeSql($sb);
            }
        }

        if ($this->parts->limit !== null) {
            $sb->writeString(' LIMIT ');
            $this->parts->limit->writeSql($sb);
        }

        if ($this->parts->offset !== null) {
            $sb->writeString(' OFFSET ');
            $this->parts->offset->writeSql($sb);
        }

        if ($this->parts->lockingClause !== null) {
            $sb->writeString(' ');
            $this->parts->lockingClause->writeSql($sb);
        }
    }

    private function writeWithQueries(SqlBuilder $sb): void
    {
        $hasRecursive = false;
        foreach ($this->withQueries as $w) {
            if ($w->recursive) {
                $hasRecursive = true;
                break;
            }
        }

        // RECURSIVE is written once, right after WITH, and applies to all queries.
        $sb->writeString($hasRecursive ? 'WITH RECURSIVE ' : 'WITH ');
        foreach ($this->withQueries as $i => $w) {
            if ($i > 0) {
                $sb->writeString(',');
            }
            $w->writeSql($sb);
        }
        $sb->writeString(' ');
    }

    private function writeSelectParts(SqlBuilder $sb, SelectQueryParts $parts): void
    {
        // Accumulate literal SQL into $s and only flush before a nested writer
        // needs to write (and once at the very end).
        $sb->writeString('SELECT ');
        $s = '';
        $needComma = false;

        if ($parts->distinct) {
            if ($parts->distinctOn !== []) {
                $sb->writeString('DISTINCT ON (');
                foreach ($parts->distinctOn as $i => $exp) {
                    if ($i > 0) {
                        $sb->writeString(',');
                    }
                    $exp->writeSql($sb);
                }
                $s = ') ';
            } else {
                $s = 'DISTINCT ';
            }
        }

        if ($parts->selectJson !== null) {
            $sb->writeString($s);
            $s = '';

            $parts->selectJson->writeSql($sb);
            if ($parts->selectJsonAlias !== '') {
                $s = ' AS ' . $parts->selectJsonAlias;
            }
            $needComma = true;
        }

        foreach ($parts->selectList as $out) {
            if ($needComma) {
                $s .= ',';
            }
            $sb->writeString($s);
            $s = '';

            $out->exp->writeSql($sb);

            if ($out->alias !== '') {
                $s = ' AS ' . $out->alias;
            }
            $needComma = true;
        }

        if ($parts->from !== []) {
            $s .= ' FROM ';
            foreach ($parts->from as $i => $fromItem) {
                if ($i > 0) {
                    // A join attaches to the previous item; a plain item is comma-separated.
                    $s .= $fromItem->from instanceof Join ? ' ' : ',';
                }
                $sb->writeString($s);
                $s = '';

                $fromItem->writeSql($sb);
            }
        }

        if ($parts->whereConjunction !== []) {
            $sb->writeString($s . ' WHERE ');
            $s = '';

            Junction::and(...$parts->whereConjunction)->writeSql($sb);
        }

        if ($parts->groupBys !== []) {
            $sb->writeString($s . ' GROUP BY ' . ($parts->groupByDistinct ? 'DISTINCT ' : ''));
            $s = '';

            foreach ($parts->groupBys as $i => $groupBy) {
                if ($i > 0) {
                    $sb->writeString(',');
                }
                $groupBy->writeSql($sb);
            }
        }

        if ($parts->havingConjunction !== []) {
            $sb->writeString($s . ' HAVING ');
            $s = '';

            Junction::and(...$parts->havingConjunction)->writeSql($sb);
        }

        if ($s !== '') {
            $sb->writeString($s);
        }
    }
}
