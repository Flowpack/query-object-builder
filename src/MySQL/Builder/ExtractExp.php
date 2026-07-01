<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * An `EXTRACT(unit FROM source)` expression (e.g. `EXTRACT(YEAR FROM d)`).
 *
 * @internal
 */
final class ExtractExp extends ExpBase
{
    public function __construct(
        private readonly string $unit,
        private readonly Exp $from,
    ) {
    }

    public function writeSql(SqlBuilder $sb): void
    {
        $sb->writeString('EXTRACT(' . $this->unit . ' FROM ');
        $this->from->writeSql($sb);
        $sb->writeString(')');
    }
}
