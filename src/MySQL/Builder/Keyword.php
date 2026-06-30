<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * A bare SQL keyword token written verbatim, e.g. the unit of `TIMESTAMPDIFF(DAY,
 * a, b)`. Constructed only by the facade with controlled keyword values.
 *
 * @internal
 */
final class Keyword implements Exp
{
    public function __construct(
        private readonly string $keyword,
    ) {
    }

    public function writeSql(SqlBuilder $sb): void
    {
        $sb->writeString($this->keyword);
    }
}
