<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * A single `WHEN condition THEN result` branch of a CASE expression.
 *
 * @internal
 */
final class CaseCondition
{
    public function __construct(
        public readonly Exp $condition,
        public readonly ?Exp $result = null,
    ) {
    }
}
