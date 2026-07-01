<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * A type name, used as the target of a {@see CastExp} (`CAST(expr AS type)`).
 *
 * Validated against the MySQL/MariaDB CAST target-type set when the query is
 * built (unless validation is disabled). This is a different, much smaller
 * vocabulary than column types: integers cast via `SIGNED`/`UNSIGNED`.
 *
 * @internal
 */
final class TypeExp implements Exp
{
    private const VALID_TYPE_REGEX = '~^(?:'
        . '(?:BINARY|CHAR|NCHAR|VARCHAR)(?:\s*\(\s*\d+\s*\))?(?:\s+CHARACTER\s+SET\s+[A-Za-z0-9_]+)?'
        . '|NATIONAL\s+CHAR(?:\s*\(\s*\d+\s*\))?'
        . '|(?:DECIMAL|DEC|NUMERIC|FIXED)(?:\s*\(\s*\d+\s*(?:,\s*\d+\s*)?\))?'
        . '|(?:DATETIME|TIME|FLOAT)(?:\s*\(\s*\d+\s*\))?'
        . '|DATE|YEAR|JSON|DOUBLE|REAL'
        . '|SIGNED(?:\s+INTEGER)?|UNSIGNED(?:\s+INTEGER)?'
        . ')$~i';

    public function __construct(
        private readonly string $type,
    ) {
    }

    public function writeSql(SqlBuilder $sb): void
    {
        if ($sb->isValidating() && !self::isValidType($this->type)) {
            $sb->addError(new QueryBuilderException(sprintf('type: invalid: %s', $this->type)));

            return;
        }

        $sb->writeString($this->type);
    }

    private static function isValidType(string $s): bool
    {
        return preg_match(self::VALID_TYPE_REGEX, trim($s)) === 1;
    }
}
