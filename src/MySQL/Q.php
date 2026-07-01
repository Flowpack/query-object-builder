<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL;

use Flowpack\QueryObjectBuilder\MySQL\Builder\DeleteBuilder;
use Flowpack\QueryObjectBuilder\MySQL\Builder\Exp;
use Flowpack\QueryObjectBuilder\MySQL\Builder\IdentExp;
use Flowpack\QueryObjectBuilder\MySQL\Builder\InsertBuilder;
use Flowpack\QueryObjectBuilder\MySQL\Builder\ReplaceBuilder;
use Flowpack\QueryObjectBuilder\MySQL\Builder\SelectBuilder;
use Flowpack\QueryObjectBuilder\MySQL\Builder\SelectSelectBuilder;
use Flowpack\QueryObjectBuilder\MySQL\Builder\UpdateBuilder;
use Flowpack\QueryObjectBuilder\MySQL\Builder\WithQueryItem;
use Flowpack\QueryObjectBuilder\MySQL\Builder\WithWithBuilder;

/**
 * Entry point (facade) for building MySQL queries.
 *
 * The dialect-agnostic expression surface lives in {@see BuildsExpressions}; this
 * facade adds the statement entry points (SELECT, INSERT, REPLACE, UPDATE, DELETE
 * and WITH).
 */
final class Q
{
    use BuildsExpressions;

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
     * Start an INSERT statement into the given table.
     */
    public static function insertInto(IdentExp $tableName): InsertBuilder
    {
        return new InsertBuilder($tableName);
    }

    /**
     * Start a REPLACE statement into the given table.
     */
    public static function replaceInto(IdentExp $tableName): ReplaceBuilder
    {
        return new ReplaceBuilder($tableName);
    }

    /**
     * Start an UPDATE statement on the given table.
     */
    public static function update(IdentExp $tableName): UpdateBuilder
    {
        return new UpdateBuilder($tableName);
    }

    /**
     * Start a DELETE statement on the given table.
     */
    public static function deleteFrom(IdentExp $tableName): DeleteBuilder
    {
        return new DeleteBuilder($tableName);
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
}
