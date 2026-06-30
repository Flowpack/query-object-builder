<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * Operator precedence lookup; a higher number binds tighter. Used to decide when
 * an operand must be parenthesized.
 *
 * See https://dev.mysql.com/doc/refman/8.4/en/operator-precedence.html. Cast,
 * concat and power are rendered as function calls (atomic), so only the infix
 * operators that survive need an entry here.
 *
 * @internal
 */
final class Precedence
{
    /** @var array<string, int> */
    private const MAP = [
        // JSON path operators bind tightest among the operators we render.
        '->' => 6,
        '->>' => 6,
        '*' => 4,
        '/' => 4,
        '%' => 4,
        'DIV' => 4,
        'MOD' => 4,
        '+' => 3,
        '-' => 3,
        // comparison row (default 0): = <=> < > <= >= <> IS LIKE REGEXP IN ...
        'BETWEEN' => -1,
        'NOT' => -2,
        'AND' => -3,
        'XOR' => -4,
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
