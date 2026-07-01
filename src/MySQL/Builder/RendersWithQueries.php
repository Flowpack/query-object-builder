<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * Renders a leading `WITH [RECURSIVE] ...` clause.
 *
 * @internal
 */
trait RendersWithQueries
{
    /**
     * @param list<WithQueryItem> $withQueries
     */
    protected function writeWithQueries(SqlBuilder $sb, array $withQueries): void
    {
        $hasRecursive = false;
        foreach ($withQueries as $w) {
            if ($w->recursive) {
                $hasRecursive = true;
                break;
            }
        }

        // RECURSIVE is written once, right after WITH, and applies to all queries.
        $sb->writeString($hasRecursive ? 'WITH RECURSIVE ' : 'WITH ');
        foreach ($withQueries as $i => $w) {
            if ($i > 0) {
                $sb->writeString(',');
            }
            $w->writeSql($sb);
        }
        $sb->writeString(' ');
    }
}
