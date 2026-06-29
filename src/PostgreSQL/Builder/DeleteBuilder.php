<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * Builds a DELETE statement.
 *
 * Immutable: every method returns a new instance and the receiver is never
 * modified. A derived copy is assembled only by {@see derive()}.
 */
class DeleteBuilder implements InnerSqlWriter, WithQuery
{
    use RendersWithQueries;
    use RendersReturning;
    use WritesParenthesizedSql;

    /**
     * @param list<WithQueryItem> $withQueries the leading WITH clause, if any
     * @param list<FromItem> $using
     * @param list<Exp> $whereConjunction conditions joined with AND
     * @param list<ReturningItem> $returningItems
     */
    public function __construct(
        protected readonly IdentExp $tableName,
        protected readonly array $withQueries = [],
        protected readonly string $alias = '',
        protected readonly array $using = [],
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
     * Add a USING item (additional tables referenced in the WHERE clause).
     */
    public function using(FromExp $from): FromDeleteBuilder
    {
        return $this->derive(FromDeleteBuilder::class, using: [...$this->using, new FromItem($from)]);
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
     * {@see ReturningDeleteBuilder::as()}. Call multiple times for more expressions.
     */
    public function returning(Exp $outputExpression): ReturningDeleteBuilder
    {
        return $this->derive(ReturningDeleteBuilder::class, returningItems: [...$this->returningItems, new ReturningItem($outputExpression)]);
    }

    /**
     * @template T of DeleteBuilder
     * @param class-string<T> $class
     * @param list<FromItem>|null $using
     * @param list<Exp>|null $whereConjunction
     * @param list<ReturningItem>|null $returningItems
     * @return T
     */
    protected function derive(
        string $class,
        ?string $alias = null,
        ?array $using = null,
        ?array $whereConjunction = null,
        ?array $returningItems = null,
    ): DeleteBuilder {
        return new $class(
            $this->tableName,
            $this->withQueries,
            $alias ?? $this->alias,
            $using ?? $this->using,
            $whereConjunction ?? $this->whereConjunction,
            $returningItems ?? $this->returningItems,
        );
    }

    /**
     * @internal
     */
    public function innerWriteSql(SqlBuilder $sb): void
    {
        if ($this->withQueries !== []) {
            $this->writeWithQueries($sb, $this->withQueries);
        }

        $sb->writeString('DELETE FROM ');
        $this->tableName->writeSql($sb);

        if ($this->alias !== '') {
            $sb->writeString(' AS ' . $this->alias);
        }

        if ($this->using !== []) {
            $sb->writeString(' USING ');
            foreach ($this->using as $i => $fromItem) {
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
