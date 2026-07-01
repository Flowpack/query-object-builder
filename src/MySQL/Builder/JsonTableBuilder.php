<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * Builds a `JSON_TABLE(doc, 'path' COLUMNS (...))` table function for the FROM
 * clause, expanding a JSON document into rows. Define the columns with
 * {@see columns()} and give it an alias via the from item's `as()`
 * (`->from(Q::jsonTable(...))->as('jt')`).
 *
 * It is a {@see FromExp}, not a general expression: `JSON_TABLE` is only valid in a
 * FROM clause.
 *
 * Immutable: every method returns a new instance.
 */
final class JsonTableBuilder implements FromExp
{
    public function __construct(
        private readonly Exp $doc,
        private readonly string $path,
        private readonly JsonTableColumns $columns = new JsonTableColumns(),
    ) {
    }

    /**
     * Define the column list. The closure receives a {@see JsonTableColumns} builder
     * and returns it configured.
     *
     * @param \Closure(JsonTableColumns): JsonTableColumns $build
     */
    public function columns(\Closure $build): self
    {
        return new self($this->doc, $this->path, $build(new JsonTableColumns()));
    }

    public function writeSql(SqlBuilder $sb): void
    {
        $sb->writeString('JSON_TABLE(');
        $this->doc->writeSql($sb);
        $sb->writeString(', ');
        (new StringLiteral($this->path))->writeSql($sb);

        $sb->writeString(' COLUMNS (');
        $this->columns->writeColumns($sb);
        $sb->writeString('))');
    }
}
