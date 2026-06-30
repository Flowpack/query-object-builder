<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

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
    use RendersWithQueries;
    use WritesParenthesizedSql;

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
     * Add a table / subquery to the FROM clause.
     */
    public function from(FromExp $from): FromSelectBuilder
    {
        return $this->derive(FromSelectBuilder::class, from: [...$this->parts->from, new FromItem($from)]);
    }

    /**
     * Add a `LATERAL` subquery to the FROM clause (MySQL only).
     */
    public function fromLateral(FromLateralExp $from): FromSelectBuilder
    {
        return $this->derive(FromSelectBuilder::class, from: [...$this->parts->from, new FromItem($from, lateral: true)]);
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
     * Add expressions to the GROUP BY clause. Refine with
     * {@see GroupBySelectBuilder::withRollup()}.
     */
    public function groupBy(Exp ...$exps): GroupBySelectBuilder
    {
        return $this->derive(GroupBySelectBuilder::class, groupBys: [...$this->parts->groupBys, ...array_values($exps)]);
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

    public function forShare(): ForSelectBuilder
    {
        return $this->derive(ForSelectBuilder::class, lockingClause: new LockingClause('SHARE'));
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
     * @template T of SelectBuilder
     * @param class-string<T> $class
     * @param list<OutputExpr>|null $selectList
     * @param list<FromItem>|null $from
     * @param list<Exp>|null $whereConjunction
     * @param list<Exp>|null $groupBys
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
        ?array $selectList = null,
        ?array $from = null,
        ?array $whereConjunction = null,
        ?array $groupBys = null,
        ?bool $groupByWithRollup = null,
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
            selectList: $selectList ?? $this->parts->selectList,
            from: $from ?? $this->parts->from,
            whereConjunction: $whereConjunction ?? $this->parts->whereConjunction,
            groupBys: $groupBys ?? $this->parts->groupBys,
            groupByWithRollup: $groupByWithRollup ?? $this->parts->groupByWithRollup,
            havingConjunction: $havingConjunction ?? $this->parts->havingConjunction,
            orderBys: $orderBys ?? $this->parts->orderBys,
            limit: $limit ?? $this->parts->limit,
            offset: $offset ?? $this->parts->offset,
            lockingClause: $lockingClause ?? $this->parts->lockingClause,
        );

        return new $class($parts, $withQueries ?? $this->withQueries, $combinations ?? $this->combinations);
    }

    /**
     * Write the select without the surrounding parentheses (the top-level query).
     *
     * @internal
     */
    public function innerWriteSql(SqlBuilder $sb): void
    {
        if ($this->withQueries !== []) {
            $this->writeWithQueries($sb, $this->withQueries);
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

    private function writeSelectParts(SqlBuilder $sb, SelectQueryParts $parts): void
    {
        // Accumulate literal SQL into $s and only flush before a nested writer
        // needs to write (and once at the very end).
        $sb->writeString('SELECT ');
        $s = $parts->distinct ? 'DISTINCT ' : '';
        $needComma = false;

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
            $sb->writeString($s . ' GROUP BY ');
            $s = '';

            foreach ($parts->groupBys as $i => $groupBy) {
                if ($i > 0) {
                    $sb->writeString(',');
                }
                $groupBy->writeSql($sb);
            }
            if ($parts->groupByWithRollup) {
                $s = ' WITH ROLLUP';
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
