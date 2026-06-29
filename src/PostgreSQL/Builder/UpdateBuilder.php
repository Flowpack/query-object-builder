<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * Builds an UPDATE statement.
 *
 * Immutable: every method returns a new instance and the receiver is never
 * modified. A derived copy is assembled only by {@see derive()}.
 */
class UpdateBuilder implements InnerSqlWriter, WithQuery
{
    use RendersWithQueries;
    use RendersReturning;

    /**
     * @param list<WithQueryItem> $withQueries the leading WITH clause, if any
     * @param list<UpdateSetItem> $setItems
     * @param list<FromItem> $from
     * @param list<Exp> $whereConjunction conditions joined with AND
     * @param list<ReturningItem> $returningItems
     */
    public function __construct(
        protected readonly IdentExp $tableName,
        protected readonly array $withQueries = [],
        protected readonly string $alias = '',
        protected readonly array $setItems = [],
        protected readonly array $from = [],
        protected readonly array $whereConjunction = [],
        protected readonly array $returningItems = [],
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
     * Add a `SET column = value` assignment.
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
     * Add a FROM item (additional tables referenced in the SET / WHERE clauses).
     */
    public function from(FromExp $from): FromUpdateBuilder
    {
        return $this->derive(FromUpdateBuilder::class, from: [...$this->from, new FromItem($from)]);
    }

    /**
     * Add a WHERE condition. Multiple calls are joined with AND.
     */
    public function where(Exp $cond): self
    {
        return $this->derive(self::class, whereConjunction: [...$this->whereConjunction, $cond]);
    }

    /**
     * Add a RETURNING expression. Set its output name via
     * {@see ReturningUpdateBuilder::as()}. Call multiple times for more expressions.
     */
    public function returning(Exp $outputExpression): ReturningUpdateBuilder
    {
        return $this->derive(ReturningUpdateBuilder::class, returningItems: [...$this->returningItems, new ReturningItem($outputExpression)]);
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
     * @param list<UpdateSetItem>|null $setItems
     * @param list<FromItem>|null $from
     * @param list<Exp>|null $whereConjunction
     * @param list<ReturningItem>|null $returningItems
     * @return T
     */
    protected function derive(
        string $class,
        ?string $alias = null,
        ?array $setItems = null,
        ?array $from = null,
        ?array $whereConjunction = null,
        ?array $returningItems = null,
    ): UpdateBuilder {
        return new $class(
            $this->tableName,
            $this->withQueries,
            $alias ?? $this->alias,
            $setItems ?? $this->setItems,
            $from ?? $this->from,
            $whereConjunction ?? $this->whereConjunction,
            $returningItems ?? $this->returningItems,
        );
    }

    /**
     * Write the update wrapped in parentheses (as a WITH body / subquery).
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
     * @internal
     */
    public function innerWriteSql(SqlBuilder $sb): void
    {
        if ($this->withQueries !== []) {
            $this->writeWithQueries($sb, $this->withQueries);
        }

        $sb->writeString('UPDATE ');
        $this->tableName->writeSql($sb);

        $sb->writeString(($this->alias !== '' ? ' AS ' . $this->alias : '') . ' SET ');
        foreach ($this->setItems as $i => $setItem) {
            if ($i > 0) {
                $sb->writeString(',');
            }
            $setItem->writeSql($sb);
        }

        if ($this->from !== []) {
            $sb->writeString(' FROM ');
            foreach ($this->from as $i => $fromItem) {
                if ($i > 0) {
                    $sb->writeString(',');
                }
                $fromItem->writeSql($sb);
            }
        }

        if ($this->whereConjunction !== []) {
            $sb->writeString(' WHERE ');
            Junction::and(...$this->whereConjunction)->writeSql($sb);
        }

        if ($this->returningItems !== []) {
            $this->writeReturning($sb, $this->returningItems);
        }
    }
}
