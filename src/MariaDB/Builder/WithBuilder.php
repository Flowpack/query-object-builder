<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MariaDB\Builder;

use Flowpack\QueryObjectBuilder\MySQL\Builder\Exp;
use Flowpack\QueryObjectBuilder\MySQL\Builder\WithQueryItem;

/**
 * Builds a WITH clause; once the queries are defined, continue with the SELECT
 * statement via {@see select()}. (Leading `WITH` on UPDATE / DELETE is not
 * available on the MariaDB 11.x anchor.)
 */
final class WithBuilder
{
    /**
     * @param list<WithQueryItem> $withQueries
     */
    public function __construct(
        private readonly array $withQueries,
    ) {
    }

    /**
     * The WITH query items, for appending to another select via
     * {@see SelectBuilder::appendWith()}.
     *
     * @return list<WithQueryItem>
     */
    public function withQueryItems(): array
    {
        return $this->withQueries;
    }

    /**
     * Add another WITH query (its body is supplied via {@see WithWithBuilder::as()}).
     */
    public function with(string $queryName): WithWithBuilder
    {
        return $this->startWithQuery($queryName, false);
    }

    /**
     * Add another WITH RECURSIVE query.
     */
    public function withRecursive(string $queryName): WithWithBuilder
    {
        return $this->startWithQuery($queryName, true);
    }

    private function startWithQuery(string $queryName, bool $recursive): WithWithBuilder
    {
        return new WithWithBuilder([...$this->withQueries, new WithQueryItem($recursive, $queryName)]);
    }

    /**
     * Start the SELECT statement following the WITH clause.
     */
    public function select(Exp ...$exps): SelectSelectBuilder
    {
        return (new SelectBuilder(withQueries: $this->withQueries))->select(...$exps);
    }
}
