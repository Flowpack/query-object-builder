<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * A single `JSON_TABLE` column. Depending on its {@see JsonTableColumnKind} it
 * renders as `name type PATH 'p' [on_empty] [on_error]`, `name type EXISTS PATH
 * 'p'`, `name FOR ORDINALITY`, or `NESTED PATH 'p' COLUMNS (...)`.
 *
 * @internal
 */
final class JsonTableColumn
{
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly JsonTableColumnKind $kind,
        public readonly string $path,
        public readonly ?JsonTableOnClause $onEmpty,
        public readonly ?JsonTableOnClause $onError,
        public readonly ?JsonTableColumns $nested,
    ) {
    }

    public function writeSql(SqlBuilder $sb): void
    {
        if ($this->kind === JsonTableColumnKind::Nested) {
            assert($this->nested !== null);
            $sb->writeString('NESTED PATH ');
            (new StringLiteral($this->path))->writeSql($sb);
            $sb->writeString(' COLUMNS (');
            $this->nested->writeColumns($sb);
            $sb->writeString(')');

            return;
        }

        $name = Keywords::quoteIdentifierIfKeyword($this->name);
        if ($this->kind === JsonTableColumnKind::Ordinality) {
            $sb->writeString($name . ' FOR ORDINALITY');

            return;
        }

        $keyword = $this->kind === JsonTableColumnKind::Exists ? ' EXISTS PATH ' : ' PATH ';
        $sb->writeString($name . ' ' . $this->type . $keyword);
        (new StringLiteral($this->path))->writeSql($sb);

        // ON EMPTY / ON ERROR apply to a plain value (PATH) column only, and MySQL
        // requires ON EMPTY before ON ERROR.
        if ($this->kind === JsonTableColumnKind::Path) {
            $this->onEmpty?->writeSql($sb, 'EMPTY');
            $this->onError?->writeSql($sb, 'ERROR');
        }
    }
}
