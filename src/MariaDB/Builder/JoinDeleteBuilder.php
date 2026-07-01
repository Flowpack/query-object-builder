<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MariaDB\Builder;

use Flowpack\QueryObjectBuilder\MySQL\Builder\RefinesLastDeleteJoin;

/**
 * The DELETE builder state right after joining a table, where {@see as()},
 * {@see on()} and {@see using()} act on that last join.
 */
final class JoinDeleteBuilder extends DeleteBuilder
{
    use RefinesLastDeleteJoin;
}
