<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * A `TRIM(...)` expression in any of its forms: `TRIM(str)`,
 * `TRIM(remstr FROM str)`, or `TRIM({BOTH|LEADING|TRAILING} remstr FROM str)`.
 *
 * @internal
 */
final class TrimExp extends ExpBase
{
    public function __construct(
        private readonly Exp $str,
        private readonly string $direction = '',
        private readonly ?Exp $remstr = null,
    ) {
    }

    public function writeSql(SqlBuilder $sb): void
    {
        $sb->writeString('TRIM(');

        $s = '';
        if ($this->direction !== '') {
            $s = $this->direction . ' ';
        }
        if ($this->remstr !== null) {
            $sb->writeString($s);
            $this->remstr->writeSql($sb);
            $s = ' ';
        }
        // A direction or a remove-string introduces the FROM separator.
        if ($this->direction !== '' || $this->remstr !== null) {
            $sb->writeString($s . 'FROM ');
        }

        $this->str->writeSql($sb);
        $sb->writeString(')');
    }
}
