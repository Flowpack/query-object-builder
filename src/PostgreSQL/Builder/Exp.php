<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * Marker interface for an SQL expression (something that can appear in a
 * select list, a WHERE condition, a function argument, etc.).
 */
interface Exp extends SqlWriter
{
}
