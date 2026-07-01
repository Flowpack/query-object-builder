<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * Helpers for quoting SQL literals.
 *
 * @internal
 */
final class Literals
{
    private function __construct()
    {
    }

    /**
     * Quote a literal value to be embedded directly in an SQL statement (e.g. a
     * JSON object key). The single quote is doubled and the backslash is escaped:
     * MySQL/MariaDB treat the backslash as an escape character under the default
     * sql_mode.
     */
    public static function quoteLiteral(string $literal): string
    {
        return "'" . str_replace(['\\', "'"], ['\\\\', "''"], $literal) . "'";
    }
}
