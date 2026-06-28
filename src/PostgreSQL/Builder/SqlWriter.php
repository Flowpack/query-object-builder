<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * Something that can render itself as (a fragment of) SQL into a {@see SqlBuilder}.
 *
 * It is the contract every expression, clause and builder satisfies, and the
 * type {@see QueryBuilder} builds from.
 */
interface SqlWriter
{
    /**
     * @internal The rendering contract; build queries through the facade and
     *           {@see QueryBuilder::toSql()} rather than calling this directly.
     */
    public function writeSql(SqlBuilder $sb): void;
}
