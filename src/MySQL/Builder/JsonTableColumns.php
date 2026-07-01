<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * Builds a `JSON_TABLE` column list — used both for the top-level `COLUMNS (...)`
 * and, recursively, for each `NESTED PATH ... COLUMNS (...)`.
 *
 * A column is opened with {@see column()} (a value column) or {@see nested()} (a
 * nested list); {@see path()} sets its JSON path (both forms have one),
 * {@see existsPath()} / {@see forOrdinality()} pick the leaf form, and
 * {@see columns()} supplies a nested column's child list.
 *
 * Immutable: every method returns a new instance; the "modify the last column"
 * operations all go through {@see rebuildLastColumn()}.
 */
final class JsonTableColumns
{
    /**
     * @param list<JsonTableColumn> $columns
     */
    public function __construct(
        private readonly array $columns = [],
    ) {
    }

    /**
     * Open a value column `name [type]`. Give it a path with {@see path()} or
     * {@see existsPath()}, or make it a row counter with {@see forOrdinality()}.
     */
    public function column(string $name, string $type = ''): self
    {
        return new self([...$this->columns, new JsonTableColumn($name, $type, JsonTableColumnKind::Path, '', null, null, null)]);
    }

    /**
     * Set the JSON path of the last column (`PATH 'path'`, or the `NESTED PATH` of a
     * {@see nested()} column).
     */
    public function path(string $path): self
    {
        return $this->rebuildLastColumn(path: $path);
    }

    /**
     * Make the last column an existence flag (`type EXISTS PATH 'path'`).
     */
    public function existsPath(string $path): self
    {
        return $this->rebuildLastColumn(kind: JsonTableColumnKind::Exists, path: $path);
    }

    /**
     * Make the last column the row counter (`name FOR ORDINALITY`).
     */
    public function forOrdinality(): self
    {
        return $this->rebuildLastColumn(kind: JsonTableColumnKind::Ordinality);
    }

    public function nullOnEmpty(): self
    {
        return $this->rebuildLastColumn(onEmpty: new JsonTableOnClause('NULL'));
    }

    public function defaultOnEmpty(string $value): self
    {
        return $this->rebuildLastColumn(onEmpty: new JsonTableOnClause('DEFAULT', $value));
    }

    public function errorOnEmpty(): self
    {
        return $this->rebuildLastColumn(onEmpty: new JsonTableOnClause('ERROR'));
    }

    public function nullOnError(): self
    {
        return $this->rebuildLastColumn(onError: new JsonTableOnClause('NULL'));
    }

    public function defaultOnError(string $value): self
    {
        return $this->rebuildLastColumn(onError: new JsonTableOnClause('DEFAULT', $value));
    }

    public function errorOnError(): self
    {
        return $this->rebuildLastColumn(onError: new JsonTableOnClause('ERROR'));
    }

    /**
     * Open a nested column. Set its path with {@see path()} and its child column
     * list with {@see columns()}.
     */
    public function nested(): self
    {
        return new self([...$this->columns, new JsonTableColumn('', '', JsonTableColumnKind::Nested, '', null, null, null)]);
    }

    /**
     * Supply the child column list of the last (nested) column.
     *
     * @param \Closure(JsonTableColumns): JsonTableColumns $build
     */
    public function columns(\Closure $build): self
    {
        return $this->rebuildLastColumn(nested: $build(new self()));
    }

    /**
     * @internal
     */
    public function writeColumns(SqlBuilder $sb): void
    {
        foreach ($this->columns as $i => $column) {
            if ($i > 0) {
                $sb->writeString(',');
            }
            $column->writeSql($sb);
        }
    }

    private function rebuildLastColumn(
        ?JsonTableColumnKind $kind = null,
        ?string $path = null,
        ?JsonTableOnClause $onEmpty = null,
        ?JsonTableOnClause $onError = null,
        ?JsonTableColumns $nested = null,
    ): self {
        $columns = $this->columns;
        $lastIdx = array_key_last($columns);
        assert($lastIdx !== null);

        $c = $columns[$lastIdx];
        $columns[$lastIdx] = new JsonTableColumn(
            $c->name,
            $c->type,
            $kind ?? $c->kind,
            $path ?? $c->path,
            $onEmpty ?? $c->onEmpty,
            $onError ?? $c->onError,
            $nested ?? $c->nested,
        );

        return new self($columns);
    }
}
