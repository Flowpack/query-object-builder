<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * A single `JSON_TABLE` column: either `name type PATH 'jsonpath'` or the
 * row-counter `name FOR ORDINALITY`.
 *
 * @internal
 */
final class JsonTableColumn
{
    private function __construct(
        private readonly string $name,
        private readonly bool $forOrdinality,
        private readonly string $type,
        private readonly string $path,
    ) {
    }

    public static function path(string $name, string $type, string $path): self
    {
        return new self($name, false, $type, $path);
    }

    public static function ordinality(string $name): self
    {
        return new self($name, true, '', '');
    }

    public function writeSql(SqlBuilder $sb): void
    {
        $name = Keywords::quoteIdentifierIfKeyword($this->name);
        if ($this->forOrdinality) {
            $sb->writeString($name . ' FOR ORDINALITY');

            return;
        }

        $sb->writeString($name . ' ' . $this->type . ' PATH ');
        (new StringLiteral($this->path))->writeSql($sb);
    }
}
