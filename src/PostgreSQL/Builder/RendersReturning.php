<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * Renders a trailing `RETURNING ...` clause, shared by the INSERT / UPDATE /
 * DELETE builders.
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
        $sb->writeString(' RETURNING ');
        foreach ($returningItems as $i => $item) {
            if ($i > 0) {
                $sb->writeString(',');
            }
            $item->writeSql($sb);
        }
    }
}
