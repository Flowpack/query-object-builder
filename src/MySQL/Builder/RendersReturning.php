<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * Renders a trailing `RETURNING ...` clause (MariaDB INSERT / REPLACE / DELETE).
 *
 * @internal
 */
trait RendersReturning
{
    /**
     * @param list<ReturningItem> $returningItems
     */
    protected function writeReturning(SqlBuilder $sb, array $returningItems): void
    {
        $sb->requireDialect(Dialect::MariaDb, 'RETURNING');
        $sb->writeString(' RETURNING ');
        foreach ($returningItems as $i => $item) {
            if ($i > 0) {
                $sb->writeString(',');
            }
            $item->writeSql($sb);
        }
    }
}
