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
class SelectBuilder implements InnerSqlWriter, WithQuery, Exp
{
    /**
     * @param list<WithQueryItem> $withQueries the leading WITH clause, if any
     */
    public function __construct(
        protected readonly SelectQueryParts $parts = new SelectQueryParts(),
        protected readonly array $withQueries = [],
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
     */
    public function groupBy(Exp ...$exps): SelectBuilder
    {
        return $this->derive(SelectBuilder::class, groupBys: [...$this->parts->groupBys, new GroupingElement(array_values($exps))]);
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
     * Append the given WITH queries to this select's WITH clause.
     */
    public function appendWith(WithBuilder $with): SelectBuilder
    {
        return $this->derive(SelectBuilder::class, withQueries: [...$this->withQueries, ...$with->withQueryItems()]);
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
     * @param list<GroupingElement>|null $groupBys
     * @param list<OrderByClause>|null $orderBys
     * @param list<WithQueryItem>|null $withQueries
     * @return T
     */
    protected function derive(
        string $class,
        ?JsonBuildObjectBuilder $selectJson = null,
        ?string $selectJsonAlias = null,
        ?array $selectList = null,
        ?array $from = null,
        ?array $whereConjunction = null,
        ?array $groupBys = null,
        ?array $orderBys = null,
        ?Exp $limit = null,
        ?Exp $offset = null,
        ?array $withQueries = null,
    ): SelectBuilder {
        $parts = new SelectQueryParts(
            $selectJson ?? $this->parts->selectJson,
            $selectJsonAlias ?? $this->parts->selectJsonAlias,
            $selectList ?? $this->parts->selectList,
            $from ?? $this->parts->from,
            $whereConjunction ?? $this->parts->whereConjunction,
            $groupBys ?? $this->parts->groupBys,
            $orderBys ?? $this->parts->orderBys,
            $limit ?? $this->parts->limit,
            $offset ?? $this->parts->offset,
        );

        return new $class($parts, $withQueries ?? $this->withQueries);
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

        if ($parts->selectJson !== null) {
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
            $sb->writeString($s . ' GROUP BY ');
            $s = '';

            foreach ($parts->groupBys as $i => $groupBy) {
                if ($i > 0) {
                    $sb->writeString(',');
                }
                $groupBy->writeSql($sb);
            }
        }

        if ($s !== '') {
            $sb->writeString($s);
        }
    }
}
