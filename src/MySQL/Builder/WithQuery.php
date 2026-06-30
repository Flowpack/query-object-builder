<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * Marker interface for something that can be used as the body of a WITH query.
 */
interface WithQuery extends SqlWriter
{
}
