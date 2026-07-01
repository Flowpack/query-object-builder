<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * An `ON EMPTY` / `ON ERROR` behaviour of a `JSON_TABLE` value column:
 * `NULL`, `ERROR`, or `DEFAULT json_string`.
 *
 * @internal
 */
final class JsonTableOnClause
{
    public function __construct(
        public readonly string $behavior,
        public readonly ?string $default = null,
    ) {
    }

    /**
     * @param string $event `EMPTY` or `ERROR`
     */
    public function writeSql(SqlBuilder $sb, string $event): void
    {
        if ($this->default !== null) {
            $sb->writeString(' DEFAULT ');
            (new StringLiteral($this->default))->writeSql($sb);
        } else {
            $sb->writeString(' ' . $this->behavior);
        }
        $sb->writeString(' ON ' . $event);
    }
}
