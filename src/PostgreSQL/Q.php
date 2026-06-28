<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL;

use Flowpack\QueryObjectBuilder\PostgreSQL\Builder\Arg;
use Flowpack\QueryObjectBuilder\PostgreSQL\Builder\ArrayExp;
use Flowpack\QueryObjectBuilder\PostgreSQL\Builder\BoolLiteral;
use Flowpack\QueryObjectBuilder\PostgreSQL\Builder\Exp;
use Flowpack\QueryObjectBuilder\PostgreSQL\Builder\Expressions;
use Flowpack\QueryObjectBuilder\PostgreSQL\Builder\ExistsExp;
use Flowpack\QueryObjectBuilder\PostgreSQL\Builder\FuncBuilder;
use Flowpack\QueryObjectBuilder\PostgreSQL\Builder\FuncExp;
use Flowpack\QueryObjectBuilder\PostgreSQL\Builder\IdentExp;
use Flowpack\QueryObjectBuilder\PostgreSQL\Builder\IntervalExp;
use Flowpack\QueryObjectBuilder\PostgreSQL\Builder\IntLiteral;
use Flowpack\QueryObjectBuilder\PostgreSQL\Builder\JsonBuildObjectBuilder;
use Flowpack\QueryObjectBuilder\PostgreSQL\Builder\Junction;
use Flowpack\QueryObjectBuilder\PostgreSQL\Builder\Precedence;
use Flowpack\QueryObjectBuilder\PostgreSQL\Builder\QueryBuilder;
use Flowpack\QueryObjectBuilder\PostgreSQL\Builder\RowsFromBuilder;
use Flowpack\QueryObjectBuilder\PostgreSQL\Builder\SelectBuilder;
use Flowpack\QueryObjectBuilder\PostgreSQL\Builder\SelectJsonSelectBuilder;
use Flowpack\QueryObjectBuilder\PostgreSQL\Builder\SelectSelectBuilder;
use Flowpack\QueryObjectBuilder\PostgreSQL\Builder\SqlWriter;
use Flowpack\QueryObjectBuilder\PostgreSQL\Builder\StringLiteral;
use Flowpack\QueryObjectBuilder\PostgreSQL\Builder\SubqueryExp;
use Flowpack\QueryObjectBuilder\PostgreSQL\Builder\UnaryExp;
use Flowpack\QueryObjectBuilder\PostgreSQL\Builder\WithQueryItem;
use Flowpack\QueryObjectBuilder\PostgreSQL\Builder\WithWithBuilder;

/**
 * Entry point (facade) for building PostgreSQL queries.
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
     * Select the given output expressions and start a new select builder.
     */
    public static function select(Exp ...$exps): SelectSelectBuilder
    {
        return (new SelectBuilder())->select(...$exps);
    }

    /**
     * Start a select builder with the given JSON object as its (first) selection.
     */
    public static function selectJson(JsonBuildObjectBuilder $obj): SelectJsonSelectBuilder
    {
        return (new SelectBuilder())->applySelectJson(static fn (JsonBuildObjectBuilder $existing): JsonBuildObjectBuilder => $obj);
    }

    /**
     * Build a `COALESCE(...)` expression.
     */
    public static function coalesce(Exp $exp, Exp ...$rest): FuncExp
    {
        return new FuncExp('COALESCE', array_values([$exp, ...$rest]));
    }

    /**
     * A function call expression, usable in the select list or in a FROM clause.
     */
    public static function func(string $name, Exp ...$args): FuncBuilder
    {
        return new FuncBuilder($name, array_values($args));
    }

    /**
     * A `ROWS FROM ( ... )` FROM item combining several set-returning functions.
     */
    public static function rowsFrom(FuncBuilder $fn, FuncBuilder ...$fns): RowsFromBuilder
    {
        return new RowsFromBuilder(array_values([$fn, ...$fns]));
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
     * An `EXISTS (subquery)` expression.
     */
    public static function exists(SelectBuilder $subquery): ExistsExp
    {
        return new ExistsExp($subquery);
    }

    /**
     * An `ANY (...)` row/array comparison operand.
     */
    public static function any(Exp $exp): SubqueryExp
    {
        return new SubqueryExp('ANY', $exp);
    }

    /**
     * An `ALL (...)` row/array comparison operand.
     */
    public static function all(Exp $exp): SubqueryExp
    {
        return new SubqueryExp('ALL', $exp);
    }

    /**
     * A boolean literal (`true` / `false`).
     */
    public static function bool(bool $b): BoolLiteral
    {
        return new BoolLiteral($b);
    }

    /**
     * An array literal, e.g. `ARRAY[1, 2, 3]`. All elements should share a type.
     */
    public static function array(Exp ...$exps): ArrayExp
    {
        return new ArrayExp(array_values($exps));
    }

    /**
     * An interval constant, e.g. `INTERVAL '5 hours'`.
     */
    public static function interval(string $spec): IntervalExp
    {
        return new IntervalExp($spec);
    }

    /**
     * A parenthesized list of expressions, e.g. for `IN (...)`.
     */
    public static function exps(Exp ...$exps): Expressions
    {
        return new Expressions(array_values($exps));
    }

    /**
     * A parenthesized list of bound arguments, e.g. for `IN ($1, $2, $3)`.
     */
    public static function args(mixed ...$arguments): Expressions
    {
        return new Expressions(array_map(static fn (mixed $a): Arg => new Arg($a), array_values($arguments)));
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
     * Write the given name / identifier (validated when the query is built).
     */
    public static function n(string $s): IdentExp
    {
        return IdentExp::n($s);
    }

    /**
     * Start a WITH clause. Supply the query body via {@see WithWithBuilder::as()}.
     */
    public static function with(string $queryName): WithWithBuilder
    {
        return new WithWithBuilder([new WithQueryItem(false, $queryName)]);
    }

    /**
     * Start a WITH RECURSIVE clause.
     */
    public static function withRecursive(string $queryName): WithWithBuilder
    {
        return new WithWithBuilder([new WithQueryItem(true, $queryName)]);
    }

    /**
     * Create a bound argument expression (a positional placeholder).
     */
    public static function arg(mixed $argument): Arg
    {
        return new Arg($argument);
    }

    /**
     * Start a new query builder for the given writer.
     */
    public static function build(SqlWriter $writer): QueryBuilder
    {
        return QueryBuilder::build($writer);
    }
}
