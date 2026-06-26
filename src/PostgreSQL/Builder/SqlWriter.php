<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * A SqlWriter knows how to write itself as (a fragment of) SQL into a {@see SqlBuilder}.
 *
 * This is the central abstraction of the query builder: every expression, clause
 * and builder ultimately implements it.
 */
interface SqlWriter
{
    public function writeSql(SqlBuilder $sb): void;
}
