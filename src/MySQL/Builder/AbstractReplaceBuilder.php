<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * Shared foundation of the REPLACE builder for the MySQL family: like INSERT, but
 * an existing row with the same primary or unique key is deleted before the new
 * row is inserted. There is no `ON DUPLICATE KEY UPDATE`. `RETURNING` is available
 * only on the dialect that exposes {@see returning()}; the field is carried here so
 * the shared rendering can emit it when present.
 *
 * Immutable: every method returns a new instance; a derived copy is assembled only
 * by {@see derive()}.
 */
abstract class AbstractReplaceBuilder implements InnerSqlWriter
{
    use RendersReturning;

    /**
     * @param list<string> $columnNames
     * @param list<list<Exp>> $valueLists
     * @param list<ReturningItem> $returningItems
     */
    public function __construct(
        protected readonly IdentExp $tableName,
        protected readonly array $columnNames = [],
        protected readonly bool $defaultValues = false,
        protected readonly array $valueLists = [],
        protected readonly ?AbstractSelectBuilder $query = null,
        protected readonly array $returningItems = [],
    ) {
    }

    /**
     * Set the column names to replace into.
     */
    public function columnNames(string $columnName, string ...$rest): static
    {
        return $this->derive(static::class, columnNames: array_values([$columnName, ...$rest]));
    }

    /**
     * Replace with a row consisting entirely of default values, rendered as
     * `() VALUES ()`. Calling {@see values()} afterwards overrules this.
     */
    public function defaultValues(): static
    {
        return $this->derive(static::class, defaultValues: true);
    }

    /**
     * Append a row of values. Call multiple times to replace several rows.
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
     * Replace with the result of the given select query.
     */
    public function query(AbstractSelectBuilder $query): static
    {
        return $this->derive(static::class, query: $query);
    }

    /**
     * @template T of AbstractReplaceBuilder
     * @param class-string<T> $class
     * @param list<string>|null $columnNames
     * @param list<list<Exp>>|null $valueLists
     * @param list<ReturningItem>|null $returningItems
     * @return T
     */
    protected function derive(
        string $class,
        ?array $columnNames = null,
        ?bool $defaultValues = null,
        ?array $valueLists = null,
        ?AbstractSelectBuilder $query = null,
        ?array $returningItems = null,
    ): AbstractReplaceBuilder {
        return new $class(
            $this->tableName,
            $columnNames ?? $this->columnNames,
            $defaultValues ?? $this->defaultValues,
            $valueLists ?? $this->valueLists,
            $query ?? $this->query,
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
        $sb->writeString('REPLACE INTO ');
        $this->tableName->writeSql($sb);

        if ($this->valueLists !== [] && $this->query !== null) {
            $sb->addError(new QueryBuilderException('replace: cannot set both values and query'));

            return;
        }

        if ($this->defaultValues) {
            $sb->writeString(' () VALUES ()');
        } else {
            $this->writeColumnNames($sb);
            $this->writeSource($sb);
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
