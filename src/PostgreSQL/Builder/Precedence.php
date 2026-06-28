<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * Operator precedence lookup; a higher number binds tighter. Used to decide when
 * an operand must be parenthesized.
 *
 * See https://www.postgresql.org/docs/current/sql-syntax-lexical.html#SQL-PRECEDENCE.
 *
 * @internal
 */
final class Precedence
{
    /** @var array<string, int> */
    private const MAP = [
        '.' => 7,
        '::' => 6,
        // 5: [ ] array element selection
        // 4: unary plus / minus
        '^' => 3,
        '*' => 2,
        '/' => 2,
        '%' => 2,
        '+' => 1,
        '-' => 1,
        // any other operator defaults to 0 (see self::of())
        'BETWEEN' => -1,
        'IN' => -1,
        'LIKE' => -1,
        'ILIKE' => -1,
        'SIMILAR' => -1,
        '<' => -2,
        '>' => -2,
        '=' => -2,
        '<=' => -2,
        '>=' => -2,
        '<>' => -2,
        'IS' => -3,
        'ISNULL' => -3,
        'NOTNULL' => -3,
        'IS DISTINCT FROM' => -3,
        'IS NOT DISTINCT FROM' => -3,
        'NOT' => -4,
        'AND' => -5,
        'OR' => -5,
    ];

    private function __construct()
    {
    }

    public static function of(string $op): int
    {
        return self::MAP[$op] ?? 0;
    }
}
