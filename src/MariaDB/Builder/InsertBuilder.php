<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MariaDB\Builder;

use Flowpack\QueryObjectBuilder\MySQL\Builder\AbstractInsertBuilder;
use Flowpack\QueryObjectBuilder\MySQL\Builder\Exp;
use Flowpack\QueryObjectBuilder\MySQL\Builder\ReturningItem;

/**
 * Builds a MariaDB INSERT statement. Adds the `ON DUPLICATE KEY UPDATE` and
 * `RETURNING` entry points. The proposed row is referenced via `Q::inserted('col')`
 * (rendered as `VALUES(col)`), so no row alias precedes the upsert assignments.
 */
class InsertBuilder extends AbstractInsertBuilder
{
    /**
     * Add an `ON DUPLICATE KEY UPDATE` clause. Reference the row that would have
     * been inserted via `Q::inserted('col')` (rendered as `VALUES(col)`).
     */
    public function onDuplicateKeyUpdate(): OnDuplicateKeyUpdateInsertBuilder
    {
        return $this->derive(OnDuplicateKeyUpdateInsertBuilder::class);
    }

    /**
     * Add a RETURNING clause. Refine the output name of the last expression via
     * {@see ReturningInsertBuilder::as()}.
     */
    public function returning(Exp $outputExpression, Exp ...$exps): ReturningInsertBuilder
    {
        $returningItems = $this->returningItems;
        foreach ([$outputExpression, ...array_values($exps)] as $exp) {
            $returningItems[] = new ReturningItem($exp);
        }

        return $this->derive(ReturningInsertBuilder::class, returningItems: $returningItems);
    }
}
