<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * Shared foundation of the INSERT builder: state, the single
 * {@see derive()} assembly point, the shared row-source methods, and the rendering.
 *
 * The proposed-row alias that precedes `ON DUPLICATE KEY UPDATE` is a dialect
 * concern ({@see writeUpsertRowAlias()}), and `RETURNING` is available only on the
 * dialect that exposes {@see returning()}; the field is carried here so the shared
 * rendering can emit it when present.
 *
 * Immutable: every method returns a new instance; a derived copy is assembled only
 * by {@see derive()}.
 */
abstract class AbstractInsertBuilder implements InnerSqlWriter
{
    use RendersReturning;

    /**
     * @param list<string> $columnNames
     * @param list<list<Exp>> $valueLists
     * @param list<UpdateSetItem> $onDuplicateKeyUpdateSetItems
     * @param list<ReturningItem> $returningItems
     */
    public function __construct(
        protected readonly IdentExp $tableName,
        protected readonly bool $ignore = false,
        protected readonly array $columnNames = [],
        protected readonly bool $defaultValues = false,
        protected readonly array $valueLists = [],
        protected readonly ?AbstractSelectBuilder $query = null,
        protected readonly array $onDuplicateKeyUpdateSetItems = [],
        protected readonly array $returningItems = [],
    ) {
    }

    /**
     * Demote insert errors (e.g. duplicate-key, foreign-key) to warnings so the
     * offending rows are skipped instead of aborting the statement.
     */
    public function ignore(): static
    {
        return $this->derive(static::class, ignore: true);
    }

    /**
     * Set the column names to insert into.
     */
    public function columnNames(string $columnName, string ...$rest): static
    {
        return $this->derive(static::class, columnNames: array_values([$columnName, ...$rest]));
    }

    /**
     * Insert a row consisting entirely of default values, rendered as `() VALUES ()`.
     * Calling {@see values()} afterwards overrules this.
     */
    public function defaultValues(): static
    {
        return $this->derive(static::class, defaultValues: true);
    }

    /**
     * Append a row of values to insert. Call multiple times to insert several rows.
     */
    public function values(Exp ...$values): static
    {
        return $this->derive(static::class, valueLists: [...$this->valueLists, array_values($values)]);
    }

    /**
     * Set the column names and values from the given map (column name => value).
     * Values are bound as arguments and the column order is stable (sorted by name).
     * Overwrites any previous column names and values.
     *
     * @param array<string, mixed> $map
     */
    public function setMap(array $map): static
    {
        ksort($map, SORT_STRING);

        $values = [];
        foreach ($map as $value) {
            $values[] = new Arg($value);
        }

        return $this->derive(static::class, columnNames: array_keys($map), valueLists: [$values]);
    }

    /**
     * Insert the result of the given select query.
     */
    public function query(AbstractSelectBuilder $query): static
    {
        return $this->derive(static::class, query: $query);
    }

    /**
     * Assemble a new builder of the given type with the given fields replaced; a
     * null argument keeps the current value.
     *
     * @template T of AbstractInsertBuilder
     * @param class-string<T> $class
     * @param list<string>|null $columnNames
     * @param list<list<Exp>>|null $valueLists
     * @param list<UpdateSetItem>|null $onDuplicateKeyUpdateSetItems
     * @param list<ReturningItem>|null $returningItems
     * @return T
     */
    protected function derive(
        string $class,
        ?bool $ignore = null,
        ?array $columnNames = null,
        ?bool $defaultValues = null,
        ?array $valueLists = null,
        ?AbstractSelectBuilder $query = null,
        ?array $onDuplicateKeyUpdateSetItems = null,
        ?array $returningItems = null,
    ): AbstractInsertBuilder {
        return new $class(
            $this->tableName,
            $ignore ?? $this->ignore,
            $columnNames ?? $this->columnNames,
            $defaultValues ?? $this->defaultValues,
            $valueLists ?? $this->valueLists,
            $query ?? $this->query,
            $onDuplicateKeyUpdateSetItems ?? $this->onDuplicateKeyUpdateSetItems,
            $returningItems ?? $this->returningItems,
        );
    }

    /**
     * Emit the proposed-row alias that precedes `ON DUPLICATE KEY UPDATE`, if the
     * dialect uses one. Called only when there are upsert assignments.
     */
    protected function writeUpsertRowAlias(SqlBuilder $sb): void
    {
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
        $sb->writeString($this->ignore ? 'INSERT IGNORE INTO ' : 'INSERT INTO ');
        $this->tableName->writeSql($sb);

        if ($this->valueLists !== [] && $this->query !== null) {
            $sb->addError(new QueryBuilderException('insert: cannot set both values and query'));

            return;
        }

        // A row of all defaults is the empty column list with an empty value row.
        if ($this->defaultValues) {
            $sb->writeString(' () VALUES ()');
        } else {
            $this->writeColumnNames($sb);
            $this->writeSource($sb);
        }

        if ($this->onDuplicateKeyUpdateSetItems !== []) {
            $this->writeUpsertRowAlias($sb);

            $sb->writeString(' ON DUPLICATE KEY UPDATE ');
            foreach ($this->onDuplicateKeyUpdateSetItems as $i => $item) {
                if ($i > 0) {
                    $sb->writeString(',');
                }
                $item->writeSql($sb);
            }
        }

        if ($this->returningItems !== []) {
            $this->writeReturning($sb, $this->returningItems);
        }
    }

    private function writeColumnNames(SqlBuilder $sb): void
    {
        if ($this->columnNames === []) {
            return;
        }

        $s = ' (';
        foreach ($this->columnNames as $i => $columnName) {
            $s .= ($i > 0 ? ',' : '') . Keywords::quoteIdentifierIfKeyword($columnName);
        }
        $sb->writeString($s . ')');
    }

    private function writeSource(SqlBuilder $sb): void
    {
        if ($this->query !== null) {
            $sb->writeString(' ');
            $this->query->innerWriteSql($sb);

            return;
        }

        if ($this->valueLists === []) {
            return;
        }

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
    }
}
