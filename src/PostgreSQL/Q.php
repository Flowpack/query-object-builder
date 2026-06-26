<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL;

use Flowpack\QueryObjectBuilder\PostgreSQL\Builder\Arg;
use Flowpack\QueryObjectBuilder\PostgreSQL\Builder\Exp;
use Flowpack\QueryObjectBuilder\PostgreSQL\Builder\IdentExp;
use Flowpack\QueryObjectBuilder\PostgreSQL\Builder\QueryBuilder;
use Flowpack\QueryObjectBuilder\PostgreSQL\Builder\SelectBuilder;
use Flowpack\QueryObjectBuilder\PostgreSQL\Builder\SelectSelectBuilder;
use Flowpack\QueryObjectBuilder\PostgreSQL\Builder\SqlWriter;
use Flowpack\QueryObjectBuilder\PostgreSQL\Builder\WithQueryItem;
use Flowpack\QueryObjectBuilder\PostgreSQL\Builder\WithWithBuilder;

/**
 * Entry point (facade) for building PostgreSQL queries.
 *
 * It exposes the builder package as a small set of static functions so that the
 * builder types and interfaces don't have to be referenced directly. This
 * mirrors the Go `qrb` root package, scoped to the PostgreSQL dialect.
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
