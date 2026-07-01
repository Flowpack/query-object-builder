<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MariaDB\Builder;

use Flowpack\QueryObjectBuilder\MySQL\Builder\RefinesLastJoin;

/**
 * The builder state right after adding a join to the FROM clause, where
 * {@see RefinesLastJoin::as()}, {@see RefinesLastJoin::on()} and
 * {@see RefinesLastJoin::using()} act on that last join.
 */
final class JoinSelectBuilder extends SelectBuilder
{
    use RefinesLastJoin;
}
