<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * Marker interface for a FROM item that may be prefixed with `LATERAL`
 * (a function call or a subquery — not a plain table name).
 *
 * LATERAL is supported by MySQL (8.0.14+) but not MariaDB.
 */
interface FromLateralExp extends FromExp
{
}
