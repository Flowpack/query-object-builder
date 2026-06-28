<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * Marker interface for a FROM item that may be prefixed with `LATERAL`
 * (a function call, ROWS FROM, or a subquery — not a plain table name).
 */
interface FromLateralExp extends FromExp
{
}
