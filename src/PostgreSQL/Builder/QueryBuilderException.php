<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * Thrown when building a query fails (e.g. an invalid identifier or a missing
 * named argument).
 */
class QueryBuilderException extends \RuntimeException
{
}
