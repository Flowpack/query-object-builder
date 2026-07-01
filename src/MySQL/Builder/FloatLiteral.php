<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * A floating-point literal expression (e.g. `0.5`).
 */
final class FloatLiteral implements Exp
{
    public function __construct(
        private readonly float $value,
    ) {
    }

    public function writeSql(SqlBuilder $sb): void
    {
        // Render the shortest decimal form without an exponent.
        $s = rtrim(rtrim(sprintf('%.15g', $this->value), '0'), '.');
        $sb->writeString($s === '' || $s === '-' ? '0' : $s);
    }
}
