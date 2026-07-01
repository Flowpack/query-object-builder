<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * Marker interface for a FROM item that may be prefixed with `LATERAL`
 * (a subquery — not a plain table name).
 */
interface FromLateralExp extends FromExp
{
}
