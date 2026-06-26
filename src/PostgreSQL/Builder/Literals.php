<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * Helpers for quoting SQL literals.
 */
final class Literals
{
    private function __construct()
    {
    }

    /**
     * Quote a literal value to be embedded directly in an SQL statement (e.g. a
     * JSON object key). Single quotes are doubled; if the value contains
     * backslashes they are escaped and the C-style escape syntax (` E'...'`) is
     * used.
     *
     * Port of the Go `builder.pqQuoteLiteral` (borrowed from lib/pq).
     */
    public static function quoteLiteral(string $literal): string
    {
        $literal = str_replace("'", "''", $literal);

        if (str_contains($literal, '\\')) {
            $literal = str_replace('\\', '\\\\', $literal);

            return " E'" . $literal . "'";
        }

        return "'" . $literal . "'";
    }
}
