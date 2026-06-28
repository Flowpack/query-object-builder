<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * Builds a SELECT query.
 *
 * This is the PHP adaptation of the Go `builder.SelectBuilder`. It follows the
 * same two principles:
 *
 *  - Immutability: every method returns a new builder; the receiver is never
 *    modified. State lives in {@see SelectQueryParts}, which is cloned before
 *    being changed.
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
        protected SelectQueryParts $parts = new SelectQueryParts(),
        protected array $withQueries = [],
    ) {
    }

    /**
     * Apply a function to the JSON selection (an empty json_build_object if none
     * set yet). The JSON selection is always written as the first select element.
     *
     * @param callable(JsonBuildObjectBuilder): JsonBuildObjectBuilder $apply
     */
    public function applySelectJson(callable $apply): SelectJsonSelectBuilder
    {
        $parts = clone $this->parts;
        $parts->selectJson = $apply($parts->selectJson ?? new JsonBuildObjectBuilder(false));

        return $this->into(SelectJsonSelectBuilder::class, $parts);
    }

    /**
     * Add the given expressions to the select list.
     */
    public function select(Exp ...$exps): SelectSelectBuilder
    {
        $parts = clone $this->parts;
        foreach ($exps as $exp) {
            $parts->selectList[] = new OutputExpr($exp);
        }

        return $this->into(SelectSelectBuilder::class, $parts);
    }

    /**
     * Add a table / function / subquery to the FROM clause.
     */
    public function from(FromExp $from): FromSelectBuilder
    {
        $parts = clone $this->parts;
        $parts->from[] = new FromItem($from);

        return $this->into(FromSelectBuilder::class, $parts);
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
        $parts = clone $this->parts;
        $parts->from[] = new FromItem(new Join($joinType, $lateral, $from));

        return $this->into(JoinSelectBuilder::class, $parts);
    }

    /**
     * Add a WHERE condition. Multiple calls are joined with AND.
     */
    public function where(Exp $cond): SelectBuilder
    {
        $parts = clone $this->parts;
        $parts->whereConjunction[] = $cond;

        return $this->into(SelectBuilder::class, $parts);
    }

    /**
     * Add a grouping element for the given expressions to the GROUP BY clause.
     */
    public function groupBy(Exp ...$exps): SelectBuilder
    {
        $parts = clone $this->parts;
        $parts->groupBys[] = new GroupingElement(array_values($exps));

        return $this->into(SelectBuilder::class, $parts);
    }

    /**
     * Add an ORDER BY expression (refine it via {@see OrderBySelectBuilder}).
     */
    public function orderBy(Exp $exp): OrderBySelectBuilder
    {
        $parts = clone $this->parts;
        $parts->orderBys[] = new OrderByClause($exp);

        return $this->into(OrderBySelectBuilder::class, $parts);
    }

    public function limit(Exp $exp): SelectBuilder
    {
        $parts = clone $this->parts;
        $parts->limit = $exp;

        return $this->into(SelectBuilder::class, $parts);
    }

    public function offset(Exp $exp): SelectBuilder
    {
        $parts = clone $this->parts;
        $parts->offset = $exp;

        return $this->into(SelectBuilder::class, $parts);
    }

    /**
     * Append the given WITH queries to this select's WITH clause.
     */
    public function appendWith(WithBuilder $with): SelectBuilder
    {
        return new SelectBuilder($this->parts, [...$this->withQueries, ...$with->withQueryItems()]);
    }

    /**
     * Create a new builder of the given type carrying the given parts.
     *
     * This is how the type-state transitions are implemented: the state is
     * copied into a fresh instance of the target builder class.
     *
     * @template T of SelectBuilder
     * @param class-string<T> $class
     * @return T
     */
    protected function into(string $class, SelectQueryParts $parts): SelectBuilder
    {
        return new $class($parts, $this->withQueries);
    }

    // --- SqlWriter / InnerSqlWriter

    /**
     * Write the select as an expression (i.e. a subquery), wrapped in parentheses.
     */
    public function writeSql(SqlBuilder $sb): void
    {
        $sb->writeString('(');
        $this->innerWriteSql($sb);
        $sb->writeString(')');
    }

    /**
     * Write the select without the surrounding parentheses (top-level query).
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

        // From the docs: when there are multiple queries in the WITH clause,
        // RECURSIVE is written only once, immediately after WITH.
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
        // Accumulate literal SQL into $s and only emit to the builder right
        // before a nested writer needs to write (and once at the very end).
        $sb->writeString('SELECT ');
        $s = '';
        $needComma = false;

        // The JSON selection, if any, is always the first select element.
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
                    // Joins are written adjacent to the previous item, plain
                    // from items are comma-separated.
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
