<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * Builds an UPDATE statement.
 *
 * A single-table update may carry `ORDER BY` and `LIMIT`; joining further tables
 * via {@see join()} turns it into a multi-table update, where those clauses are
 * not allowed.
 *
 * Immutable: every method returns a new instance and the receiver is never
 * modified. A derived copy is assembled only by {@see derive()}.
 */
class UpdateBuilder implements InnerSqlWriter
{
    use RendersWithQueries;

    /**
     * @param list<WithQueryItem> $withQueries the leading WITH clause, if any
     * @param list<FromItem> $joins additional joined tables (multi-table update)
     * @param list<UpdateSetItem> $setItems
     * @param list<Exp> $whereConjunction conditions joined with AND
     * @param list<OrderByClause> $orderBys
     */
    public function __construct(
        protected readonly IdentExp $tableName,
        protected readonly array $withQueries = [],
        protected readonly string $alias = '',
        protected readonly array $joins = [],
        protected readonly array $setItems = [],
        protected readonly array $whereConjunction = [],
        protected readonly array $orderBys = [],
        protected readonly ?Exp $limit = null,
    ) {
    }

    /**
     * Set an alias for the target table.
     */
    public function as(string $alias): self
    {
        return $this->derive(self::class, alias: $alias);
    }

    /**
     * Join another table, turning this into a multi-table update. Refine it via
     * {@see JoinUpdateBuilder::on()} / {@see JoinUpdateBuilder::using()} /
     * {@see JoinUpdateBuilder::as()}.
     */
    public function join(FromExp $from): JoinUpdateBuilder
    {
        return $this->addJoin(JoinType::Inner, $from);
    }

    public function leftJoin(FromExp $from): JoinUpdateBuilder
    {
        return $this->addJoin(JoinType::Left, $from);
    }

    public function rightJoin(FromExp $from): JoinUpdateBuilder
    {
        return $this->addJoin(JoinType::Right, $from);
    }

    public function crossJoin(FromExp $from): JoinUpdateBuilder
    {
        return $this->addJoin(JoinType::Cross, $from);
    }

    private function addJoin(JoinType $joinType, FromExp $from): JoinUpdateBuilder
    {
        return $this->derive(JoinUpdateBuilder::class, joins: [...$this->joins, new FromItem(new Join($joinType, false, $from))]);
    }

    /**
     * Add a `SET column = value` assignment. Qualify the column (e.g. `t1.col`) in
     * a multi-table update.
     */
    public function set(string $columnName, Exp $value): self
    {
        return $this->derive(self::class, setItems: [...$this->setItems, new UpdateSetItem($columnName, $value)]);
    }

    /**
     * Set the SET-clause assignments from the given map (column name => value).
     * Values are bound as arguments and the column order is stable (sorted by name).
     * Overwrites any previous assignments.
     *
     * @param array<string, mixed> $map
     */
    public function setMap(array $map): self
    {
        ksort($map, SORT_STRING);

        $setItems = [];
        foreach ($map as $columnName => $value) {
            $setItems[] = new UpdateSetItem($columnName, new Arg($value));
        }

        return $this->derive(self::class, setItems: $setItems);
    }

    /**
     * Add a WHERE condition. Multiple calls are joined with AND.
     */
    public function where(Exp $cond): self
    {
        return $this->derive(self::class, whereConjunction: [...$this->whereConjunction, $cond]);
    }

    /**
     * Add an ORDER BY expression (single-table update only). Refine it via
     * {@see OrderByUpdateBuilder}.
     */
    public function orderBy(Exp $exp): OrderByUpdateBuilder
    {
        return $this->derive(OrderByUpdateBuilder::class, orderBys: [...$this->orderBys, new OrderByClause($exp)]);
    }

    /**
     * Limit the number of rows updated (single-table update only).
     */
    public function limit(Exp $exp): self
    {
        return $this->derive(self::class, limit: $exp);
    }

    /**
     * Apply the given function to this builder if the condition is true; otherwise
     * return the builder unchanged. Helpful for conditional query building.
     *
     * @param callable(UpdateBuilder): UpdateBuilder $apply
     */
    public function applyIf(bool $cond, callable $apply): UpdateBuilder
    {
        return $cond ? $apply($this) : $this;
    }

    /**
     * @template T of UpdateBuilder
     * @param class-string<T> $class
     * @param list<FromItem>|null $joins
     * @param list<UpdateSetItem>|null $setItems
     * @param list<Exp>|null $whereConjunction
     * @param list<OrderByClause>|null $orderBys
     * @return T
     */
    protected function derive(
        string $class,
        ?string $alias = null,
        ?array $joins = null,
        ?array $setItems = null,
        ?array $whereConjunction = null,
        ?array $orderBys = null,
        ?Exp $limit = null,
    ): UpdateBuilder {
        return new $class(
            $this->tableName,
            $this->withQueries,
            $alias ?? $this->alias,
            $joins ?? $this->joins,
            $setItems ?? $this->setItems,
            $whereConjunction ?? $this->whereConjunction,
            $orderBys ?? $this->orderBys,
            $limit ?? $this->limit,
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
        // ORDER BY / LIMIT bound which rows a single-table update touches; they are
        // not part of the multi-table grammar.
        if ($this->joins !== [] && ($this->orderBys !== [] || $this->limit !== null)) {
            $sb->addError(new QueryBuilderException('update: ORDER BY / LIMIT not allowed in a multi-table update'));

            return;
        }

        if ($this->withQueries !== []) {
            $sb->requireAnyDialect('WITH before UPDATE', new Requirement(Dialect::Mysql), new Requirement(Dialect::MariaDb, gteVersion: '12.3'));
            $this->writeWithQueries($sb, $this->withQueries);
        }

        $sb->writeString('UPDATE ');
        $this->tableName->writeSql($sb);
        if ($this->alias !== '') {
            $sb->writeString(' AS ' . $this->alias);
        }
        foreach ($this->joins as $join) {
            $sb->writeString(' ');
            $join->writeSql($sb);
        }

        $sb->writeString(' SET ');
        foreach ($this->setItems as $i => $setItem) {
            if ($i > 0) {
                $sb->writeString(',');
            }
            $setItem->writeSql($sb);
        }

        if ($this->whereConjunction !== []) {
            $sb->writeString(' WHERE ');
            Junction::and(...$this->whereConjunction)->writeSql($sb);
        }

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
    }
}
