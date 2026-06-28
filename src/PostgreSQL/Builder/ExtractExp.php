<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * An `EXTRACT(field FROM source)` expression.
 */
final class ExtractExp extends ExpBase
{
    public function __construct(
        private readonly string $field,
        private readonly Exp $from,
    ) {
    }

    public function writeSql(SqlBuilder $sb): void
    {
        $sb->writeString('EXTRACT(' . $this->field . ' FROM ');
        $this->from->writeSql($sb);
        $sb->writeString(')');
    }
}
