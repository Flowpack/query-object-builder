<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * Builds an INSERT statement.
 *
 * Immutable: every method returns a new instance and the receiver is never
 * modified. A derived copy is assembled only by {@see derive()}.
 */
class InsertBuilder implements InnerSqlWriter
{
    /**
     * @param list<string> $columnNames
     * @param list<list<Exp>> $valueLists
     * @param list<UpdateSetItem> $onDuplicateKeyUpdateSetItems
     */
    public function __construct(
        protected readonly IdentExp $tableName,
        protected readonly bool $ignore = false,
        protected readonly array $columnNames = [],
        protected readonly bool $defaultValues = false,
        protected readonly array $valueLists = [],
        protected readonly ?SelectBuilder $query = null,
        protected readonly array $onDuplicateKeyUpdateSetItems = [],
    ) {
    }

    /**
     * Demote insert errors (e.g. duplicate-key, foreign-key) to warnings so the
     * offending rows are skipped instead of aborting the statement.
     */
    public function ignore(): self
    {
        return $this->derive(self::class, ignore: true);
    }

    /**
     * Set the column names to insert into.
     */
    public function columnNames(string $columnName, string ...$rest): self
    {
        return $this->derive(self::class, columnNames: array_values([$columnName, ...$rest]));
    }

    /**
     * Insert a row consisting entirely of default values, rendered as `() VALUES ()`.
     * Calling {@see values()} afterwards overrules this.
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
     * Add an `ON DUPLICATE KEY UPDATE` clause. Reference the row that would have
     * been inserted via `Q::inserted('col')`.
     */
    public function onDuplicateKeyUpdate(): OnDuplicateKeyUpdateInsertBuilder
    {
        return $this->derive(OnDuplicateKeyUpdateInsertBuilder::class);
    }

    /**
     * Assemble a new builder of the given type with the given fields replaced; a
     * null argument keeps the current value.
     *
     * @template T of InsertBuilder
     * @param class-string<T> $class
     * @param list<string>|null $columnNames
     * @param list<list<Exp>>|null $valueLists
     * @param list<UpdateSetItem>|null $onDuplicateKeyUpdateSetItems
     * @return T
     */
    protected function derive(
        string $class,
        ?bool $ignore = null,
        ?array $columnNames = null,
        ?bool $defaultValues = null,
        ?array $valueLists = null,
        ?SelectBuilder $query = null,
        ?array $onDuplicateKeyUpdateSetItems = null,
    ): InsertBuilder {
        return new $class(
            $this->tableName,
            $ignore ?? $this->ignore,
            $columnNames ?? $this->columnNames,
            $defaultValues ?? $this->defaultValues,
            $valueLists ?? $this->valueLists,
            $query ?? $this->query,
            $onDuplicateKeyUpdateSetItems ?? $this->onDuplicateKeyUpdateSetItems,
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
            $this->writeOnDuplicateKeyUpdate($sb);
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

    private function writeOnDuplicateKeyUpdate(SqlBuilder $sb): void
    {
        // The `AS new` row alias makes the proposed row reachable as `new.col` in
        // the update assignments (see `Q::inserted()`); it follows the value rows.
        if ($this->query === null) {
            $sb->writeString(' AS new');
        }

        $sb->writeString(' ON DUPLICATE KEY UPDATE ');
        foreach ($this->onDuplicateKeyUpdateSetItems as $i => $item) {
            if ($i > 0) {
                $sb->writeString(',');
            }
            $item->writeSql($sb);
        }
    }
}
