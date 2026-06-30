<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * An identifier / name expression (a column, table, alias, ... possibly as a
 * dotted path like `schema.table.column`).
 *
 * The identifier is validated when the query is built (unless validation is
 * disabled) and reserved MySQL/MariaDB keywords are automatically backtick-quoted.
 */
final class IdentExp extends ExpBase implements FromExp
{
    /**
     * Validates a (possibly dotted, possibly backtick-quoted) identifier. Each
     * part is an unquoted identifier (letters, digits, `$`, `_`, Unicode) or a
     * backtick-quoted segment; the final part may be `*`.
     */
    private const VALID_IDENTIFIER_REGEX = '~\A(?:(?:`(?:[^`]|``)+`|[0-9A-Za-z$_\x{0080}-\x{FFFF}]+)\.)*(?:`(?:[^`]|``)+`|[0-9A-Za-z$_\x{0080}-\x{FFFF}]+|\*)\z~u';

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
