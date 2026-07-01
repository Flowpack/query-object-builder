<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * A single `column = value` assignment of an UPDATE `SET` clause (also used by
 * INSERT's `ON DUPLICATE KEY UPDATE`). The column name is quoted if it is a
 * reserved keyword.
 *
 * @internal
 */
final class UpdateSetItem
{
    public function __construct(
        public readonly string $columnName,
        public readonly Exp $value,
    ) {
    }

    public function writeSql(SqlBuilder $sb): void
    {
        $sb->writeString(Keywords::quoteIdentifierIfKeyword($this->columnName) . ' = ');
        $this->value->writeSql($sb);
    }
}
