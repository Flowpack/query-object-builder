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
    /** The precedence of the unary prefixes `-` (negation) and `~` (bitwise NOT). */
    public const UNARY = 12;

    /** @var array<string, int> */
    private const MAP = [
        // JSON path operators bind tightest among the operators we render.
        '->' => 13,
        '->>' => 13,
        // (unary - and ~ sit at UNARY = 12, between the JSON operators and ^)
        '^' => 11,
        '*' => 10,
        '/' => 10,
        '%' => 10,
        'DIV' => 10,
        'MOD' => 10,
        '+' => 9,
        '-' => 9,
        '<<' => 8,
        '>>' => 8,
        '&' => 7,
        '|' => 6,
        // comparison row (default 0): = <=> < > <= >= <> IS LIKE REGEXP IN MEMBER OF ...
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
