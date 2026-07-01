<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * The builder state right after adding an ORDER BY expression, where
 * {@see asc()} / {@see desc()} set the sort direction of that last term.
 */
class OrderBySelectBuilder extends SelectBuilder
{
    use OrdersLastTerm;
}
