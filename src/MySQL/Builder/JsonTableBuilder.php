<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * Builds a `JSON_TABLE(doc, 'path' COLUMNS (...))` table function for the FROM
 * clause, expanding a JSON document into rows. Give it an alias with the from
 * item's `as()` (`->from(Q::jsonTable(...))->as('jt')`).
 *
 * It is a {@see FromExp}, not a general expression: `JSON_TABLE` is only valid in a
 * FROM clause.
 *
 * Immutable: every method returns a new instance.
 */
final class JsonTableBuilder implements FromExp
{
    /**
     * @param list<JsonTableColumn> $columns
     */
    public function __construct(
        private readonly Exp $doc,
        private readonly string $path,
        private readonly array $columns = [],
    ) {
    }

    /**
     * Add a column extracted at the given JSON path (`name type PATH 'path'`).
     */
    public function column(string $name, string $type, string $path): self
    {
        return new self($this->doc, $this->path, [...$this->columns, JsonTableColumn::path($name, $type, $path)]);
    }

    /**
     * Add a 1-based row counter column (`name FOR ORDINALITY`).
     */
    public function columnForOrdinality(string $name): self
    {
        return new self($this->doc, $this->path, [...$this->columns, JsonTableColumn::ordinality($name)]);
    }

    public function writeSql(SqlBuilder $sb): void
    {
        $sb->writeString('JSON_TABLE(');
        $this->doc->writeSql($sb);
        $sb->writeString(', ');
        (new StringLiteral($this->path))->writeSql($sb);

        $sb->writeString(' COLUMNS (');
        foreach ($this->columns as $i => $column) {
            if ($i > 0) {
                $sb->writeString(',');
            }
            $column->writeSql($sb);
        }
        $sb->writeString('))');
    }
}
