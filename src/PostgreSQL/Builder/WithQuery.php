<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * Marker interface for something that can be used as the body of a WITH query
 * (currently a {@see SelectBuilder}; later also INSERT/UPDATE/DELETE).
 */
interface WithQuery extends SqlWriter
{
}
