<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * Marker interface for something that can be used as the body of a WITH query:
 * a {@see SelectBuilder} or an {@see InsertBuilder} / {@see UpdateBuilder} /
 * {@see DeleteBuilder}.
 */
interface WithQuery extends SqlWriter
{
}
