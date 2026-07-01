<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * Builds an INSERT statement: the row source (value rows, a feeding SELECT, or a
 * default-values row), an optional `ON DUPLICATE KEY UPDATE` upsert, and an
 * optional `RETURNING` clause.
 *
 * The proposed-row alias (`AS new`, set via {@see InsertValuesBuilder::as()}) and
 * `RETURNING` mark themselves while rendering, so validating the query against a
 * {@see Target} reports the one the target cannot express.
 *
 * Immutable: every method returns a new instance; a derived copy is assembled only
 * by {@see derive()}.
 */
class InsertBuilder implements InnerSqlWriter
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
        protected readonly ?SelectBuilder $query = null,
        protected readonly string $rowAlias = '',
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
     * Continue with {@see InsertValuesBuilder::as()} to alias the proposed row.
     */
    public function values(Exp ...$values): InsertValuesBuilder
    {
        return $this->derive(InsertValuesBuilder::class, valueLists: [...$this->valueLists, array_values($values)]);
    }

    /**
     * Set the column names and values from the given map (column name => value).
     * Values are bound as arguments and the column order is stable (sorted by name).
     * Overwrites any previous column names and values.
     *
     * @param array<string, mixed> $map
     */
    public function setMap(array $map): InsertValuesBuilder
    {
        ksort($map, SORT_STRING);

        $values = [];
        foreach ($map as $value) {
            $values[] = new Arg($value);
        }

        return $this->derive(InsertValuesBuilder::class, columnNames: array_keys($map), valueLists: [$values]);
    }

    /**
     * Insert the result of the given select query.
     */
    public function query(SelectBuilder $query): static
    {
        return $this->derive(static::class, query: $query);
    }

    /**
     * Add an `ON DUPLICATE KEY UPDATE` clause. Reference the proposed row via
     * `Q::values('col')`, or via `Q::n('new.col')` after aliasing it with
     * {@see InsertValuesBuilder::as()}.
     */
    public function onDuplicateKeyUpdate(): OnDuplicateKeyUpdateInsertBuilder
    {
        return $this->derive(OnDuplicateKeyUpdateInsertBuilder::class);
    }

    /**
     * Add a RETURNING clause. Refine the output name of the last expression via
     * {@see ReturningInsertBuilder::as()}.
     */
    public function returning(Exp $outputExpression, Exp ...$exps): ReturningInsertBuilder
    {
        $returningItems = $this->returningItems;
        foreach ([$outputExpression, ...array_values($exps)] as $exp) {
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
        ?SelectBuilder $query = null,
        ?string $rowAlias = null,
        ?array $onDuplicateKeyUpdateSetItems = null,
        ?array $returningItems = null,
    ): InsertBuilder {
        return new $class(
            $this->tableName,
            $ignore ?? $this->ignore,
            $columnNames ?? $this->columnNames,
            $defaultValues ?? $this->defaultValues,
            $valueLists ?? $this->valueLists,
            $query ?? $this->query,
            $rowAlias ?? $this->rowAlias,
            $onDuplicateKeyUpdateSetItems ?? $this->onDuplicateKeyUpdateSetItems,
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

        // The row alias follows the value rows; it makes the proposed row reachable
        // as `alias.col`, and is not used with the `INSERT ... SELECT` source form.
        if ($this->rowAlias !== '' && $this->query === null) {
            $sb->requireDialect(Dialect::Mysql, 'the INSERT row alias (AS ...)');
            $sb->writeString(' AS ' . $this->rowAlias);
        }

        if ($this->onDuplicateKeyUpdateSetItems !== []) {
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
