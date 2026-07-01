<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MariaDB\Builder;

use Flowpack\QueryObjectBuilder\MySQL\Builder\AbstractReplaceBuilder;
use Flowpack\QueryObjectBuilder\MySQL\Builder\Exp;
use Flowpack\QueryObjectBuilder\MySQL\Builder\ReturningItem;

/**
 * Builds a MariaDB REPLACE statement, adding a `RETURNING` clause.
 */
class ReplaceBuilder extends AbstractReplaceBuilder
{
    /**
     * Add a RETURNING clause. Refine the output name of the last expression via
     * {@see ReturningReplaceBuilder::as()}.
     */
    public function returning(Exp $outputExpression, Exp ...$exps): ReturningReplaceBuilder
    {
        $returningItems = $this->returningItems;
        foreach ([$outputExpression, ...array_values($exps)] as $exp) {
            $returningItems[] = new ReturningItem($exp);
        }

        return $this->derive(ReturningReplaceBuilder::class, returningItems: $returningItems);
    }
}
