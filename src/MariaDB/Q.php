<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MariaDB;

use Flowpack\QueryObjectBuilder\MariaDB\Builder\DeleteBuilder;
use Flowpack\QueryObjectBuilder\MariaDB\Builder\InsertBuilder;
use Flowpack\QueryObjectBuilder\MariaDB\Builder\ReplaceBuilder;
use Flowpack\QueryObjectBuilder\MariaDB\Builder\SelectBuilder;
use Flowpack\QueryObjectBuilder\MariaDB\Builder\SelectSelectBuilder;
use Flowpack\QueryObjectBuilder\MariaDB\Builder\WithWithBuilder;
use Flowpack\QueryObjectBuilder\MySQL\Builder\Exp;
use Flowpack\QueryObjectBuilder\MySQL\Builder\FuncExp;
use Flowpack\QueryObjectBuilder\MySQL\Builder\IdentExp;
use Flowpack\QueryObjectBuilder\MySQL\Builder\UpdateBuilder;
use Flowpack\QueryObjectBuilder\MySQL\Builder\WithQueryItem;
use Flowpack\QueryObjectBuilder\MySQL\BuildsExpressions;

/**
 * Entry point (facade) for building MariaDB queries.
 *
 * The dialect-agnostic expression surface lives in {@see BuildsExpressions} (shared
 * with the MySQL facade); this facade adds MariaDB's statement entry points.
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
     * Reference the value of `column` from the row that would have been inserted,
     * for use inside `ON DUPLICATE KEY UPDATE` (rendered as `VALUES(column)`).
     */
    public static function inserted(string $column): FuncExp
    {
        return new FuncExp('VALUES', [IdentExp::n($column)]);
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
