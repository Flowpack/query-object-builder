<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MariaDB\Builder;

use Flowpack\QueryObjectBuilder\MySQL\Builder\OrdersLastTerm;

/**
 * The builder state right after adding an ORDER BY expression, where
 * {@see OrdersLastTerm::asc()} / {@see OrdersLastTerm::desc()} set the sort
 * direction of that last term.
 */
class OrderBySelectBuilder extends SelectBuilder
{
    use OrdersLastTerm;
}
