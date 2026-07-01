<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * The builder state right after adding a join to the FROM clause, where
 * {@see as()}, {@see on()} and {@see using()} act on that last join.
 */
final class JoinSelectBuilder extends SelectBuilder
{
    use RefinesLastJoin;
}
