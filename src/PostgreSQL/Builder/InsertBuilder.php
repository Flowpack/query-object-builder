<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * Builds an INSERT statement.
 *
 * Like the other builders this is immutable: every method returns a new instance
 * and the receiver is never modified. A derived copy is assembled only by
 * {@see derive()}.
 */
class InsertBuilder implements InnerSqlWriter, WithQuery
{
    use RendersWithQueries;
    use RendersReturning;
    use WritesParenthesizedSql;

    /**
     * @param list<WithQueryItem> $withQueries the leading WITH clause, if any
     * @param list<string> $columnNames
     * @param list<list<Exp>> $valueLists
     * @param list<Exp> $conflictTargets
     * @param list<Exp> $conflictTargetWhereConjunction
     * @param list<UpdateSetItem> $conflictDoUpdateSetItems
     * @param list<Exp> $conflictDoUpdateWhereConjunction
     * @param list<ReturningItem> $returningItems
     */
    public function __construct(
        protected readonly IdentExp $tableName,
        protected readonly array $withQueries = [],
        protected readonly string $alias = '',
        protected readonly array $columnNames = [],
        protected readonly bool $defaultValues = false,
        protected readonly array $valueLists = [],
        protected readonly ?SelectBuilder $query = null,
        protected readonly array $conflictTargets = [],
        protected readonly array $conflictTargetWhereConjunction = [],
        protected readonly string $conflictConstraintName = '',
        protected readonly string $conflictAction = '',
        protected readonly array $conflictDoUpdateSetItems = [],
        protected readonly array $conflictDoUpdateWhereConjunction = [],
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
     * Set the column names to insert into.
     */
    public function columnNames(string $columnName, string ...$rest): self
    {
        return $this->derive(self::class, columnNames: array_values([$columnName, ...$rest]));
    }

    /**
     * Insert a row with the table's default values. Calling {@see values()}
     * afterwards overrules this.
     */
    public function defaultValues(): self
    {
        return $this->derive(self::class, defaultValues: true);
    }

    /**
     * Append a row of values to insert. Call multiple times to insert several rows.
     */
    public function values(Exp ...$values): self
    {
        return $this->derive(self::class, valueLists: [...$this->valueLists, array_values($values)]);
    }

    /**
     * Set the column names and values from the given map (column name => value).
     * Values are bound as arguments and the column order is stable (sorted by name).
     * Overwrites any previous column names and values.
     *
     * @param array<string, mixed> $map
     */
    public function setMap(array $map): self
    {
        ksort($map, SORT_STRING);

        $values = [];
        foreach ($map as $value) {
            $values[] = new Arg($value);
        }

        return $this->derive(self::class, columnNames: array_keys($map), valueLists: [$values]);
    }

    /**
     * Insert the result of the given select query.
     */
    public function query(SelectBuilder $query): self
    {
        return $this->derive(self::class, query: $query);
    }

    /**
     * Add an ON CONFLICT clause. Pass conflict target expressions (e.g. index
     * columns), or none to add ON CONSTRAINT or DO NOTHING afterwards.
     */
    public function onConflict(Exp ...$conflictTargets): OnConflictInsertBuilder
    {
        return $this->derive(OnConflictInsertBuilder::class, conflictTargets: array_values($conflictTargets));
    }

    /**
     * Add a RETURNING clause. Refine the output name of the last expression via
     * {@see ReturningInsertBuilder::as()}.
     */
    public function returning(Exp $outputExpression, Exp ...$exps): ReturningInsertBuilder
    {
        $returningItems = $this->returningItems;
        foreach ([$outputExpression, ...$exps] as $exp) {
            $returningItems[] = new ReturningItem($exp);
        }

        return $this->derive(ReturningInsertBuilder::class, returningItems: $returningItems);
    }

    /**
     * Assemble a new builder of the given type with the given fields replaced; a
     * null argument keeps the current value.
     *
     * @template T of InsertBuilder
     * @param class-string<T> $class
     * @param list<string>|null $columnNames
     * @param list<list<Exp>>|null $valueLists
     * @param list<Exp>|null $conflictTargets
     * @param list<Exp>|null $conflictTargetWhereConjunction
     * @param list<UpdateSetItem>|null $conflictDoUpdateSetItems
     * @param list<Exp>|null $conflictDoUpdateWhereConjunction
     * @param list<ReturningItem>|null $returningItems
     * @return T
     */
    protected function derive(
        string $class,
        ?string $alias = null,
        ?array $columnNames = null,
        ?bool $defaultValues = null,
        ?array $valueLists = null,
        ?SelectBuilder $query = null,
        ?array $conflictTargets = null,
        ?array $conflictTargetWhereConjunction = null,
        ?string $conflictConstraintName = null,
        ?string $conflictAction = null,
        ?array $conflictDoUpdateSetItems = null,
        ?array $conflictDoUpdateWhereConjunction = null,
        ?array $returningItems = null,
    ): InsertBuilder {
        return new $class(
            $this->tableName,
            $this->withQueries,
            $alias ?? $this->alias,
            $columnNames ?? $this->columnNames,
            $defaultValues ?? $this->defaultValues,
            $valueLists ?? $this->valueLists,
            $query ?? $this->query,
            $conflictTargets ?? $this->conflictTargets,
            $conflictTargetWhereConjunction ?? $this->conflictTargetWhereConjunction,
            $conflictConstraintName ?? $this->conflictConstraintName,
            $conflictAction ?? $this->conflictAction,
            $conflictDoUpdateSetItems ?? $this->conflictDoUpdateSetItems,
            $conflictDoUpdateWhereConjunction ?? $this->conflictDoUpdateWhereConjunction,
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

        $sb->writeString('INSERT INTO ');
        $this->tableName->writeSql($sb);

        $s = '';
        if ($this->alias !== '') {
            $s .= ' AS ' . $this->alias;
        }
        if ($this->columnNames !== []) {
            $s .= ' (';
            foreach ($this->columnNames as $i => $columnName) {
                $s .= ($i > 0 ? ',' : '') . Keywords::quoteIdentifierIfKeyword($columnName);
            }
            $s .= ')';
        }
        $sb->writeString($s);

        if ($this->valueLists !== [] && $this->query !== null) {
            $sb->addError(new QueryBuilderException('insert: cannot set both values and query'));

            return;
        }

        if ($this->query !== null) {
            $sb->writeString(' ');
            $this->query->innerWriteSql($sb);
        } elseif ($this->valueLists !== []) {
            $sb->writeString(' VALUES ');
            foreach ($this->valueLists as $i => $valueList) {
                $sb->writeString(($i > 0 ? ',' : '') . '(');
                foreach ($valueList as $j => $value) {
                    if ($j > 0) {
                        $sb->writeString(',');
                    }
                    $value->writeSql($sb);
                }
                $sb->writeString(')');
            }
        } elseif ($this->defaultValues) {
            $sb->writeString(' DEFAULT VALUES');
        }

        if ($this->conflictAction !== '') {
            $this->writeConflict($sb);
        }

        if ($this->returningItems !== []) {
            $this->writeReturning($sb, $this->returningItems);
        }
    }

    private function writeConflict(SqlBuilder $sb): void
    {
        if ($this->conflictConstraintName !== '' && $this->conflictTargets !== []) {
            $sb->writeString(' ON CONFLICT');
            $sb->addError(new QueryBuilderException('insert: cannot set both conflict constraint name and targets'));

            return;
        }

        $s = ' ON CONFLICT';
        if ($this->conflictConstraintName !== '') {
            $s .= ' ON CONSTRAINT ' . $this->conflictConstraintName;
        }
        if ($this->conflictTargets !== []) {
            $sb->writeString($s . ' (');
            $s = '';
            foreach ($this->conflictTargets as $i => $target) {
                if ($i > 0) {
                    $sb->writeString(',');
                }
                $target->writeSql($sb);
            }
            $s .= ')';
        }
        if ($this->conflictTargetWhereConjunction !== []) {
            $sb->writeString($s . ' WHERE ');
            $s = '';
            Junction::and(...$this->conflictTargetWhereConjunction)->writeSql($sb);
        }
        $sb->writeString($s . ' ' . $this->conflictAction);

        if ($this->conflictAction === 'DO UPDATE') {
            if ($this->conflictDoUpdateSetItems !== []) {
                $sb->writeString(' SET ');
                foreach ($this->conflictDoUpdateSetItems as $i => $item) {
                    if ($i > 0) {
                        $sb->writeString(',');
                    }
                    $item->writeSql($sb);
                }
            }
            if ($this->conflictDoUpdateWhereConjunction !== []) {
                $sb->writeString(' WHERE ');
                Junction::and(...$this->conflictDoUpdateWhereConjunction)->writeSql($sb);
            }
        }
    }
}
