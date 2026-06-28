<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * Helpers for quoting identifiers that collide with PostgreSQL reserved
 * keywords.
 *
 * @internal
 */
final class Keywords
{
    /**
     * PostgreSQL reserved keywords that must be quoted when used as identifiers,
     * stored uppercase for case-insensitive lookup.
     *
     * Source: https://www.postgresql.org/docs/current/sql-keywords-appendix.html
     *
     * @var array<string, true>
     */
    private const RESERVED = [
        'ALL' => true, 'ANALYSE' => true, 'ANALYZE' => true, 'AND' => true, 'ANY' => true,
        'ARRAY' => true, 'AS' => true, 'ASYMMETRIC' => true, 'BINARY' => true, 'BOTH' => true,
        'CASE' => true, 'CAST' => true, 'CHECK' => true, 'COLLATE' => true, 'COLUMN' => true,
        'CONCURRENTLY' => true, 'CONSTRAINT' => true, 'CREATE' => true, 'CROSS' => true,
        'DEFAULT' => true, 'DEFERRABLE' => true, 'DESC' => true, 'DISTINCT' => true, 'DO' => true,
        'ELSE' => true, 'END' => true, 'EXCEPT' => true, 'FALSE' => true, 'FETCH' => true,
        'FOR' => true, 'FOREIGN' => true, 'FREEZE' => true, 'FROM' => true, 'FULL' => true,
        'GRANT' => true, 'GROUP' => true, 'HAVING' => true, 'INNER' => true, 'INTERSECT' => true,
        'INTO' => true, 'IS' => true, 'ISNULL' => true, 'JOIN' => true, 'LATERAL' => true,
        'LEADING' => true, 'LEFT' => true, 'LIKE' => true, 'LIMIT' => true, 'NOTNULL' => true,
        'NOT' => true, 'NULL' => true, 'OFFSET' => true, 'ON' => true, 'ONLY' => true, 'OR' => true,
        'ORDER' => true, 'OVERLAPS' => true, 'PLACING' => true, 'PRIMARY' => true,
        'REFERENCES' => true, 'RETURNING' => true, 'RIGHT' => true, 'SELECT' => true,
        'SIMILAR' => true, 'SOME' => true, 'SYMMETRIC' => true, 'TABLE' => true,
        'TABLESAMPLE' => true, 'THEN' => true, 'TO' => true, 'TRAILING' => true, 'TRUE' => true,
        'UNION' => true, 'UNIQUE' => true, 'USER' => true, 'USING' => true, 'VARIADIC' => true,
        'VERBOSE' => true, 'WHEN' => true, 'WHERE' => true, 'WINDOW' => true, 'WITH' => true,
    ];

    private function __construct()
    {
    }

    /**
     * Process an identifier and quote the parts that are reserved keywords.
     *
     * Handles dotted paths by processing each part individually. Parts that are
     * already quoted are left unchanged.
     */
    public static function quoteIdentifierIfKeyword(string $ident): string
    {
        if ($ident === '' || $ident === '*') {
            return $ident;
        }

        // Unicode identifier prefix (U&) - don't modify these.
        if (str_starts_with($ident, 'U&') || str_starts_with($ident, 'u&')) {
            return $ident;
        }

        $parts = self::splitIdentifier($ident);

        foreach ($parts as $i => $part) {
            if (self::isAlreadyQuoted($part) || $part === '*') {
                continue;
            }
            if (self::isReservedKeyword($part)) {
                $parts[$i] = self::quoteIdentifier($part);
            }
        }

        return implode('.', $parts);
    }

    public static function isReservedKeyword(string $s): bool
    {
        return isset(self::RESERVED[strtoupper($s)]);
    }

    /**
     * Wrap an identifier in double quotes, escaping any internal double quotes.
     */
    private static function quoteIdentifier(string $s): string
    {
        return '"' . str_replace('"', '""', $s) . '"';
    }

    private static function isAlreadyQuoted(string $s): bool
    {
        return strlen($s) >= 2 && $s[0] === '"' && $s[strlen($s) - 1] === '"';
    }

    /**
     * Split an identifier by dots, but respect quoted parts.
     * e.g. `schema."my.table".column` -> ['schema', '"my.table"', 'column']
     *
     * @return list<string>
     */
    private static function splitIdentifier(string $ident): array
    {
        $parts = [];
        $current = '';
        $inQuote = false;

        $length = strlen($ident);
        for ($i = 0; $i < $length; $i++) {
            $ch = $ident[$i];
            if ($ch === '"') {
                $inQuote = !$inQuote;
                $current .= $ch;
            } elseif ($ch === '.' && !$inQuote) {
                $parts[] = $current;
                $current = '';
            } else {
                $current .= $ch;
            }
        }

        if ($current !== '') {
            $parts[] = $current;
        }

        return $parts;
    }
}
