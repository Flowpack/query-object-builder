<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL;

use Flowpack\QueryObjectBuilder\MySQL\Builder\Arg;
use Flowpack\QueryObjectBuilder\MySQL\Builder\BindExp;
use Flowpack\QueryObjectBuilder\MySQL\Builder\BoolLiteral;
use Flowpack\QueryObjectBuilder\MySQL\Builder\DefaultLiteral;
use Flowpack\QueryObjectBuilder\MySQL\Builder\Exp;
use Flowpack\QueryObjectBuilder\MySQL\Builder\Expressions;
use Flowpack\QueryObjectBuilder\MySQL\Builder\FloatLiteral;
use Flowpack\QueryObjectBuilder\MySQL\Builder\FuncExp;
use Flowpack\QueryObjectBuilder\MySQL\Builder\IdentExp;
use Flowpack\QueryObjectBuilder\MySQL\Builder\IntLiteral;
use Flowpack\QueryObjectBuilder\MySQL\Builder\Junction;
use Flowpack\QueryObjectBuilder\MySQL\Builder\NullLiteral;
use Flowpack\QueryObjectBuilder\MySQL\Builder\Precedence;
use Flowpack\QueryObjectBuilder\MySQL\Builder\QueryBuilder;
use Flowpack\QueryObjectBuilder\MySQL\Builder\SqlWriter;
use Flowpack\QueryObjectBuilder\MySQL\Builder\StringLiteral;
use Flowpack\QueryObjectBuilder\MySQL\Builder\UnaryExp;

/**
 * Entry point (facade) for building MySQL queries.
 *
 * It exposes the builder package as a small set of static functions so the
 * underlying builder types and interfaces don't have to be referenced directly.
 */
final class Q
{
    private function __construct()
    {
    }

    /**
     * Write the given name / identifier (validated when the query is built).
     */
    public static function n(string $s): IdentExp
    {
        return IdentExp::n($s);
    }

    /**
     * A string literal.
     */
    public static function string(string $s): StringLiteral
    {
        return new StringLiteral($s);
    }

    /**
     * An integer literal.
     */
    public static function int(int $i): IntLiteral
    {
        return new IntLiteral($i);
    }

    /**
     * A floating-point literal.
     */
    public static function float(float $f): FloatLiteral
    {
        return new FloatLiteral($f);
    }

    /**
     * A boolean literal (`TRUE` / `FALSE`).
     */
    public static function bool(bool $b): BoolLiteral
    {
        return new BoolLiteral($b);
    }

    /**
     * The SQL `NULL` literal.
     */
    public static function null(): NullLiteral
    {
        return new NullLiteral();
    }

    /**
     * The SQL `DEFAULT` keyword, usable as a value in INSERT / UPDATE.
     */
    public static function default(): DefaultLiteral
    {
        return new DefaultLiteral();
    }

    /**
     * Create a bound argument expression (a positional `?` placeholder).
     */
    public static function arg(mixed $argument): Arg
    {
        return new Arg($argument);
    }

    /**
     * A named argument placeholder; bind its value via
     * {@see QueryBuilder::withNamedArgs()}. Each occurrence emits its own `?`.
     */
    public static function bind(string $name): BindExp
    {
        return new BindExp($name);
    }

    /**
     * A parenthesized list of expressions, e.g. for `IN (...)`.
     */
    public static function exps(Exp ...$exps): Expressions
    {
        return new Expressions(array_values($exps));
    }

    /**
     * A parenthesized list of bound arguments, e.g. for `IN (?, ?, ?)`.
     */
    public static function args(mixed ...$arguments): Expressions
    {
        return new Expressions(array_map(static fn (mixed $a): Arg => new Arg($a), array_values($arguments)));
    }

    /**
     * Combine the given expressions with AND.
     */
    public static function and(Exp ...$exps): Junction
    {
        return Junction::and(...$exps);
    }

    /**
     * Combine the given expressions with OR.
     */
    public static function or(Exp ...$exps): Junction
    {
        return Junction::or(...$exps);
    }

    /**
     * Negate an expression (`NOT ...`).
     */
    public static function not(Exp $exp): UnaryExp
    {
        return new UnaryExp($exp, Precedence::of('NOT'), prefix: 'NOT');
    }

    /**
     * Arithmetic negation (`- ...`) of a numeric expression.
     */
    public static function neg(Exp $exp): UnaryExp
    {
        return new UnaryExp($exp, 5, prefix: '-'); // unary minus binds tightly
    }

    /**
     * Build a `COALESCE(...)` expression.
     */
    public static function coalesce(Exp $exp, Exp ...$rest): FuncExp
    {
        return new FuncExp('COALESCE', array_values([$exp, ...$rest]));
    }

    /**
     * Start a new query builder for the given writer.
     */
    public static function build(SqlWriter $writer): QueryBuilder
    {
        return QueryBuilder::build($writer);
    }
}
