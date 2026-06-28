<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * A type name, used as the right-hand side of a cast (`expr::type`).
 *
 * The type is validated when the query is built (unless validation is disabled);
 * array notation (`int[]`), length (`varchar(255)`) and quoted names are allowed.
 *
 * @internal
 */
final class TypeExp implements Exp
{
    private const VALID_TYPE_REGEX = <<<'REGEX'
~\A((?:U&)?(?:(?:[_\p{L}][_\p{L}\p{Nd}$]{0,62}|"(?:[^"\\]|""|\\(?:\+?[0-9A-Fa-f]{4}|\+?[0-9A-Fa-f]{6}))+")\.)*(?:[_\p{L}][_\p{L}\p{Nd}$]{0,62}|"(([^"\\]|"")|\\(?:\+?[0-9A-Fa-f]{4}|\+?[0-9A-Fa-f]{6}))+")(?:\(\d+\))?(?:\s+UESCAPE\s+'[^0-9A-Fa-f"+''"[:space:]]')?(\s*\[\s*\d*\s*\])*\z)~msu
REGEX;

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
        return preg_match(self::VALID_TYPE_REGEX, $s) === 1;
    }
}
