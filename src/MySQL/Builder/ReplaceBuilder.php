<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * Builds a REPLACE statement: like INSERT, but an existing row with the same
 * primary or unique key is deleted before the new row is inserted.
 *
 * Immutable: every method returns a new instance and the receiver is never
 * modified. A derived copy is assembled only by {@see derive()}.
 */
final class ReplaceBuilder implements InnerSqlWriter
{
    /**
     * @param list<string> $columnNames
     * @param list<list<Exp>> $valueLists
     */
    public function __construct(
        private readonly IdentExp $tableName,
        private readonly array $columnNames = [],
        private readonly bool $defaultValues = false,
        private readonly array $valueLists = [],
        private readonly ?SelectBuilder $query = null,
    ) {
    }

    /**
     * Set the column names to replace into.
     */
    public function columnNames(string $columnName, string ...$rest): self
    {
        return $this->derive(columnNames: array_values([$columnName, ...$rest]));
    }

    /**
     * Replace with a row consisting entirely of default values, rendered as
     * `() VALUES ()`. Calling {@see values()} afterwards overrules this.
     */
    public function defaultValues(): self
    {
        return $this->derive(defaultValues: true);
    }

    /**
     * Append a row of values. Call multiple times to replace several rows.
     */
    public function values(Exp ...$values): self
    {
        return $this->derive(valueLists: [...$this->valueLists, array_values($values)]);
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

        return $this->derive(columnNames: array_keys($map), valueLists: [$values]);
    }

    /**
     * Replace with the result of the given select query.
     */
    public function query(SelectBuilder $query): self
    {
        return $this->derive(query: $query);
    }

    /**
     * @param list<string>|null $columnNames
     * @param list<list<Exp>>|null $valueLists
     */
    private function derive(
        ?array $columnNames = null,
        ?bool $defaultValues = null,
        ?array $valueLists = null,
        ?SelectBuilder $query = null,
    ): self {
        return new self(
            $this->tableName,
            $columnNames ?? $this->columnNames,
            $defaultValues ?? $this->defaultValues,
            $valueLists ?? $this->valueLists,
            $query ?? $this->query,
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
        $sb->writeString('REPLACE INTO ');
        $this->tableName->writeSql($sb);

        if ($this->valueLists !== [] && $this->query !== null) {
            $sb->addError(new QueryBuilderException('replace: cannot set both values and query'));

            return;
        }

        // A row of all defaults is the empty column list with an empty value row.
        if ($this->defaultValues) {
            $sb->writeString(' () VALUES ()');

            return;
        }

        $this->writeColumnNames($sb);

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
}
