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
 *  - Type-state: methods return a more specific builder type so context-dependent
 *    methods like `as()`, `using()` or `on()` are only available — and only act on
 *    the relevant element — where they make sense.
 *
 * Constructs that only one engine of the MySQL family accepts (the `LATERAL`
 * from/join family, `FOR SHARE` with its `OF` / wait-policy refinements, and the
 * `LOCK IN SHARE MODE` shared lock) mark themselves while rendering, so validating
 * the query against a {@see Target} reports the ones that target cannot express.
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
     * Whether this builder carries no content yet: no WITH queries, no
     * combinations and empty select parts. Useful for conditional query building.
     */
    public function isEmpty(): bool
    {
        return $this->withQueries === [] && $this->combinations === [] && $this->parts->isEmpty();
    }

    // Public transition methods — each returns the builder type where the following
    // context-dependent refinements make sense.

    /**
     * Add the given expressions to the select list.
     */
    public function select(Exp ...$exps): SelectSelectBuilder
    {
        return $this->addToSelectList(SelectSelectBuilder::class, array_values($exps));
    }

    /**
     * Add a table / subquery to the FROM clause.
     */
    public function from(FromExp $from): FromSelectBuilder
    {
        return $this->addFromItem(FromSelectBuilder::class, $from);
    }

    /**
     * Add a `LATERAL` subquery to the FROM clause.
     */
    public function fromLateral(FromLateralExp $from): FromSelectBuilder
    {
        return $this->addFromItem(FromSelectBuilder::class, $from, true);
    }

    public function join(FromExp $from): JoinSelectBuilder
    {
        return $this->addJoinItem(JoinSelectBuilder::class, JoinType::Inner, $from, false);
    }

    public function joinLateral(FromExp $from): JoinSelectBuilder
    {
        return $this->addJoinItem(JoinSelectBuilder::class, JoinType::Inner, $from, true);
    }

    public function leftJoin(FromExp $from): JoinSelectBuilder
    {
        return $this->addJoinItem(JoinSelectBuilder::class, JoinType::Left, $from, false);
    }

    public function leftJoinLateral(FromExp $from): JoinSelectBuilder
    {
        return $this->addJoinItem(JoinSelectBuilder::class, JoinType::Left, $from, true);
    }

    public function rightJoin(FromExp $from): JoinSelectBuilder
    {
        return $this->addJoinItem(JoinSelectBuilder::class, JoinType::Right, $from, false);
    }

    public function crossJoin(FromExp $from): JoinSelectBuilder
    {
        return $this->addJoinItem(JoinSelectBuilder::class, JoinType::Cross, $from, false);
    }

    public function crossJoinLateral(FromExp $from): JoinSelectBuilder
    {
        return $this->addJoinItem(JoinSelectBuilder::class, JoinType::Cross, $from, true);
    }

    /**
     * Add a WHERE condition. Multiple calls are joined with AND.
     */
    public function where(Exp $cond): SelectBuilder
    {
        return $this->addWhereCondition(SelectBuilder::class, $cond);
    }

    /**
     * Add expressions to the GROUP BY clause. Refine with
     * {@see GroupBySelectBuilder::withRollup()}.
     */
    public function groupBy(Exp ...$exps): GroupBySelectBuilder
    {
        return $this->addGroupByExps(GroupBySelectBuilder::class, array_values($exps));
    }

    /**
     * Add a HAVING condition. Multiple calls are joined with AND.
     */
    public function having(Exp $cond): SelectBuilder
    {
        return $this->addHavingCondition(SelectBuilder::class, $cond);
    }

    /**
     * Add a named window to the WINDOW clause. Define it via
     * {@see WindowSelectBuilder::as()} / {@see WindowSelectBuilder::partitionBy()} /
     * {@see WindowDefining::orderBy()}, then reference it from a window function
     * via {@see WindowFuncBuilder::over()}.
     */
    public function window(string $name): WindowSelectBuilder
    {
        return $this->addNamedWindow(WindowSelectBuilder::class, $name);
    }

    /**
     * Add an ORDER BY expression (refine it via {@see OrderBySelectBuilder}).
     */
    public function orderBy(Exp $exp): OrderBySelectBuilder
    {
        return $this->addOrderByExp(OrderBySelectBuilder::class, $exp);
    }

    public function limit(Exp $exp): SelectBuilder
    {
        return $this->withLimit(SelectBuilder::class, $exp);
    }

    public function offset(Exp $exp): SelectBuilder
    {
        return $this->withOffset(SelectBuilder::class, $exp);
    }

    /**
     * Combine this select with the following one using UNION. Refine with
     * {@see CombinationBuilder::all()} or supply the query via
     * {@see CombinationBuilder::query()}.
     */
    public function union(): CombinationBuilder
    {
        return $this->startCombination(CombinationBuilder::class, CombinationType::Union);
    }

    public function intersect(): CombinationBuilder
    {
        return $this->startCombination(CombinationBuilder::class, CombinationType::Intersect);
    }

    public function except(): CombinationBuilder
    {
        return $this->startCombination(CombinationBuilder::class, CombinationType::Except);
    }

    public function forUpdate(): ForSelectBuilder
    {
        return $this->withLocking(ForSelectBuilder::class, new LockingClause('FOR UPDATE'));
    }

    /**
     * Lock the selected rows for sharing (`FOR SHARE`). Refine with
     * {@see ForSelectBuilder::of()} / {@see SetsLockWaitPolicy::nowait()} /
     * {@see SetsLockWaitPolicy::skipLocked()}.
     */
    public function forShare(): ForSelectBuilder
    {
        return $this->withLocking(ForSelectBuilder::class, new LockingClause('FOR SHARE', requires: Requirement::mysql()));
    }

    /**
     * Lock the selected rows for sharing, spelled `LOCK IN SHARE MODE`.
     */
    public function lockInShareMode(): SelectBuilder
    {
        return $this->withLocking(SelectBuilder::class, new LockingClause('LOCK IN SHARE MODE', requires: Requirement::mariaDb()));
    }

    /**
     * Append the given WITH queries to this select's WITH clause.
     */
    public function appendWith(WithBuilder $with): SelectBuilder
    {
        return $this->withAppendedWith(SelectBuilder::class, $with->withQueryItems());
    }

    // Clause helpers — the field logic lives here once; the public transitions above
    // (and the type-state subbuilders) call these with their own target class and
    // declare the matching concrete return type.

    /**
     * @template T of SelectBuilder
     * @param class-string<T> $class
     * @param list<Exp> $exps
     * @return T
     */
    protected function addToSelectList(string $class, array $exps): SelectBuilder
    {
        $selectList = $this->parts->selectList;
        foreach ($exps as $exp) {
            $selectList[] = new OutputExpr($exp);
        }

        return $this->derive($class, selectList: $selectList);
    }

    /**
     * @template T of SelectBuilder
     * @param class-string<T> $class
     * @return T
     */
    protected function addFromItem(string $class, FromExp $from, bool $lateral = false): SelectBuilder
    {
        return $this->derive($class, from: [...$this->parts->from, new FromItem($from, lateral: $lateral)]);
    }

    /**
     * @template T of SelectBuilder
     * @param class-string<T> $class
     * @return T
     */
    protected function addJoinItem(string $class, JoinType $joinType, FromExp $from, bool $lateral): SelectBuilder
    {
        return $this->derive($class, from: [...$this->parts->from, new FromItem(new Join($joinType, $lateral, $from))]);
    }

    /**
     * @template T of SelectBuilder
     * @param class-string<T> $class
     * @return T
     */
    protected function addWhereCondition(string $class, Exp $cond): SelectBuilder
    {
        return $this->derive($class, whereConjunction: [...$this->parts->whereConjunction, $cond]);
    }

    /**
     * @template T of SelectBuilder
     * @param class-string<T> $class
     * @param list<Exp> $exps
     * @return T
     */
    protected function addGroupByExps(string $class, array $exps): SelectBuilder
    {
        return $this->derive($class, groupBys: [...$this->parts->groupBys, ...$exps]);
    }

    /**
     * @template T of SelectBuilder
     * @param class-string<T> $class
     * @return T
     */
    protected function addHavingCondition(string $class, Exp $cond): SelectBuilder
    {
        return $this->derive($class, havingConjunction: [...$this->parts->havingConjunction, $cond]);
    }

    /**
     * @template T of SelectBuilder
     * @param class-string<T> $class
     * @return T
     */
    protected function addOrderByExp(string $class, Exp $exp): SelectBuilder
    {
        return $this->derive($class, orderBys: [...$this->parts->orderBys, new OrderByClause($exp)]);
    }

    /**
     * @template T of SelectBuilder
     * @param class-string<T> $class
     * @return T
     */
    protected function withLimit(string $class, Exp $exp): SelectBuilder
    {
        return $this->derive($class, limit: $exp);
    }

    /**
     * @template T of SelectBuilder
     * @param class-string<T> $class
     * @return T
     */
    protected function withOffset(string $class, Exp $exp): SelectBuilder
    {
        return $this->derive($class, offset: $exp);
    }

    /**
     * @template T of SelectBuilder
     * @param class-string<T> $class
     * @return T
     */
    protected function startCombination(string $class, CombinationType $type): SelectBuilder
    {
        // Archive the current parts as a combination and start a fresh select.
        return $this->derive(
            $class,
            parts: new SelectQueryParts(),
            combinations: [...$this->combinations, new Combination($this->parts, $type)],
        );
    }

    /**
     * @template T of SelectBuilder
     * @param class-string<T> $class
     * @return T
     */
    protected function withLocking(string $class, LockingClause $lockingClause): SelectBuilder
    {
        return $this->derive($class, lockingClause: $lockingClause);
    }

    /**
     * @template T of SelectBuilder
     * @param class-string<T> $class
     * @return T
     */
    protected function addNamedWindow(string $class, string $name): SelectBuilder
    {
        return $this->derive($class, windows: [...$this->parts->windows, new NamedWindow($name)]);
    }

    /**
     * @template T of SelectBuilder
     * @param class-string<T> $class
     * @param list<WithQueryItem> $withQueries
     * @return T
     */
    protected function withAppendedWith(string $class, array $withQueries): SelectBuilder
    {
        return $this->derive($class, withQueries: [...$this->withQueries, ...$withQueries]);
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
     * @param list<NamedWindow>|null $windows
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
        ?array $windows = null,
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
            windows: $windows ?? $this->parts->windows,
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

        if ($parts->windows !== []) {
            $sb->writeString($s . ' WINDOW ');
            $s = '';

            foreach ($parts->windows as $i => $window) {
                if ($i > 0) {
                    $sb->writeString(',');
                }
                $window->writeSql($sb);
            }
        }

        if ($s !== '') {
            $sb->writeString($s);
        }
    }
}
