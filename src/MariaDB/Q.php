<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MariaDB;

use Flowpack\QueryObjectBuilder\MariaDB\Builder\SelectBuilder;
use Flowpack\QueryObjectBuilder\MariaDB\Builder\SelectSelectBuilder;
use Flowpack\QueryObjectBuilder\MariaDB\Builder\WithWithBuilder;
use Flowpack\QueryObjectBuilder\MySQL\Builder\Exp;
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
