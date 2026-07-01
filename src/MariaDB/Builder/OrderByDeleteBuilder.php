<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MariaDB\Builder;

use Flowpack\QueryObjectBuilder\MySQL\Builder\OrdersLastDeleteTerm;

/**
 * The DELETE builder state right after adding an ORDER BY expression, where
 * {@see asc()} / {@see desc()} set the sort direction of that last term.
 */
final class OrderByDeleteBuilder extends DeleteBuilder
{
    use OrdersLastDeleteTerm;
}
