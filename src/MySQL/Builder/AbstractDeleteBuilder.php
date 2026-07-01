<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * Shared foundation of the DELETE builder for the MySQL family: state, the single
 * {@see derive()} assembly point, the clause helpers and the rendering.
 *
 * A single-table delete renders `DELETE FROM tbl ...` and may carry `ORDER BY`,
 * `LIMIT` and (where the dialect exposes it) `RETURNING`. Joining further tables
 * turns it into a multi-table delete (`DELETE tbl.* FROM tbl JOIN ...`), where
 * those clauses are not allowed.
 *
 * Immutable: every method returns a new instance; a derived copy is assembled only
 * by {@see derive()}.
 */
abstract class AbstractDeleteBuilder implements InnerSqlWriter
{
    use RendersWithQueries;
    use RendersReturning;

    /**
     * @param list<WithQueryItem> $withQueries the leading WITH clause, if any
     * @param list<FromItem> $joins additional joined tables (multi-table delete)
     * @param list<Exp> $whereConjunction conditions joined with AND
     * @param list<OrderByClause> $orderBys
     * @param list<ReturningItem> $returningItems
     */
    public function __construct(
        protected readonly IdentExp $tableName,
        protected readonly array $withQueries = [],
        protected readonly string $alias = '',
        protected readonly array $joins = [],
        protected readonly array $whereConjunction = [],
        protected readonly array $orderBys = [],
        protected readonly ?Exp $limit = null,
        protected readonly array $returningItems = [],
    ) {
    }

    /**
     * Set an alias for the target table.
     */
    public function as(string $alias): static
    {
        return $this->derive(static::class, alias: $alias);
    }

    /**
     * Add a WHERE condition. Multiple calls are joined with AND.
     */
    public function where(Exp $cond): static
    {
        return $this->derive(static::class, whereConjunction: [...$this->whereConjunction, $cond]);
    }

    /**
     * Limit the number of rows deleted (single-table delete only).
     */
    public function limit(Exp $exp): static
    {
        return $this->derive(static::class, limit: $exp);
    }

    /**
     * @template T of AbstractDeleteBuilder
     * @param class-string<T> $class
     * @return T
     */
    protected function addJoin(string $class, JoinType $joinType, FromExp $from): AbstractDeleteBuilder
    {
        return $this->derive($class, joins: [...$this->joins, new FromItem(new Join($joinType, false, $from))]);
    }

    /**
     * @template T of AbstractDeleteBuilder
     * @param class-string<T> $class
     * @return T
     */
    protected function addOrderBy(string $class, Exp $exp): AbstractDeleteBuilder
    {
        return $this->derive($class, orderBys: [...$this->orderBys, new OrderByClause($exp)]);
    }

    /**
     * @template T of AbstractDeleteBuilder
     * @param class-string<T> $class
     * @param list<FromItem>|null $joins
     * @param list<Exp>|null $whereConjunction
     * @param list<OrderByClause>|null $orderBys
     * @param list<ReturningItem>|null $returningItems
     * @return T
     */
    protected function derive(
        string $class,
        ?string $alias = null,
        ?array $joins = null,
        ?array $whereConjunction = null,
        ?array $orderBys = null,
        ?Exp $limit = null,
        ?array $returningItems = null,
    ): AbstractDeleteBuilder {
        return new $class(
            $this->tableName,
            $this->withQueries,
            $alias ?? $this->alias,
            $joins ?? $this->joins,
            $whereConjunction ?? $this->whereConjunction,
            $orderBys ?? $this->orderBys,
            $limit ?? $this->limit,
            $returningItems ?? $this->returningItems,
        );
    }

    /**
     * @internal
     */
    public function writeSql(SqlBuilder $sb): void
    {
        $this->innerWriteSql($sb);
    }

    /**
     * @internal
     */
    public function innerWriteSql(SqlBuilder $sb): void
    {
        // ORDER BY / LIMIT / RETURNING bound or read a single target; they are not
        // part of the multi-table grammar.
        if ($this->joins !== [] && ($this->orderBys !== [] || $this->limit !== null || $this->returningItems !== [])) {
            $sb->addError(new QueryBuilderException('delete: ORDER BY / LIMIT / RETURNING not allowed in a multi-table delete'));

            return;
        }

        if ($this->withQueries !== []) {
            $this->writeWithQueries($sb, $this->withQueries);
        }

        if ($this->joins !== []) {
            $this->writeMultiTable($sb);

            return;
        }

        $sb->writeString('DELETE FROM ');
        $this->tableName->writeSql($sb);
        if ($this->alias !== '') {
            $sb->writeString(' AS ' . $this->alias);
        }

        $this->writeWhere($sb);

        if ($this->orderBys !== []) {
            $sb->writeString(' ORDER BY ');
            foreach ($this->orderBys as $i => $clause) {
                if ($i > 0) {
                    $sb->writeString(',');
                }
                $clause->writeSql($sb);
            }
        }

        if ($this->limit !== null) {
            $sb->writeString(' LIMIT ');
            $this->limit->writeSql($sb);
        }

        if ($this->returningItems !== []) {
            $this->writeReturning($sb, $this->returningItems);
        }
    }

    private function writeMultiTable(SqlBuilder $sb): void
    {
        // The target rows are named before FROM (`tbl.*`), the join graph after it.
        $targetRef = ($this->alias !== '' ? $this->alias : $this->tableName->ident()) . '.*';
        $sb->writeString('DELETE ');
        IdentExp::n($targetRef)->writeSql($sb);

        $sb->writeString(' FROM ');
        $this->tableName->writeSql($sb);
        if ($this->alias !== '') {
            $sb->writeString(' AS ' . $this->alias);
        }
        foreach ($this->joins as $join) {
            $sb->writeString(' ');
            $join->writeSql($sb);
        }

        $this->writeWhere($sb);
    }

    private function writeWhere(SqlBuilder $sb): void
    {
        if ($this->whereConjunction !== []) {
            $sb->writeString(' WHERE ');
            Junction::and(...$this->whereConjunction)->writeSql($sb);
        }
    }
}
