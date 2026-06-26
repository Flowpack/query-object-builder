<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * An identifier / name expression (a column, table, alias, ... possibly as a
 * dotted path like `schema.table.column`).
 *
 * The identifier is validated when the query is built (unless validation is
 * disabled) and reserved PostgreSQL keywords are automatically quoted.
 *
 * Port of the Go `builder.IdentExp` / `builder.N`.
 */
final class IdentExp implements Exp, FromExp
{
    /**
     * Validates a (possibly dotted, possibly quoted) PostgreSQL identifier.
     *
     * This will not detect every identifier that is invalid in PostgreSQL
     * (especially considering reserved keywords), but catches the common cases.
     */
    private const VALID_IDENTIFIER_REGEX = <<<'REGEX'
~\A((?:U&)?(?:(?:[_\p{L}][_\p{L}\p{Nd}$]{0,62}|"(?:[^"\\]|""|\\(?:\+?[0-9A-Fa-f]{4}|\+?[0-9A-Fa-f]{6}))+")\.)*(?:[_\p{L}][_\p{L}\p{Nd}$]{0,62}|"(([^"\\]|"")|\\(?:\+?[0-9A-Fa-f]{4}|\+?[0-9A-Fa-f]{6}))+"|\*)(?:\s+UESCAPE\s+'[^0-9A-Fa-f"+''"[:space:]]')?\z)~msu
REGEX;

    private function __construct(
        private readonly string $ident,
        private readonly string $quotedIdent,
    ) {
    }

    /**
     * Create an identifier expression for the given name.
     */
    public static function n(string $s): self
    {
        $ident = trim($s);

        return new self($ident, Keywords::quoteIdentifierIfKeyword($ident));
    }

    public function ident(): string
    {
        return $this->ident;
    }

    public function writeSql(SqlBuilder $sb): void
    {
        if ($sb->isValidating() && !self::isValidIdentifier($this->ident)) {
            $sb->addError(new QueryBuilderException(sprintf('identifier: invalid: %s', $this->ident)));

            return;
        }

        $sb->writeString($this->quotedIdent);
    }

    private static function isValidIdentifier(string $s): bool
    {
        return preg_match(self::VALID_IDENTIFIER_REGEX, $s) === 1;
    }
}
