<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MariaDB\Builder;

use Flowpack\QueryObjectBuilder\MySQL\Builder\AbstractDeleteBuilder;
use Flowpack\QueryObjectBuilder\MySQL\Builder\Exp;
use Flowpack\QueryObjectBuilder\MySQL\Builder\FromExp;
use Flowpack\QueryObjectBuilder\MySQL\Builder\JoinType;
use Flowpack\QueryObjectBuilder\MySQL\Builder\ReturningItem;

/**
 * Builds a MariaDB DELETE statement (single-table or, via {@see join()}, multi-table
 * `DELETE tbl.* FROM <refs>`), adding a single-table `RETURNING` clause.
 */
class DeleteBuilder extends AbstractDeleteBuilder
{
    public function join(FromExp $from): JoinDeleteBuilder
    {
        return $this->addJoin(JoinDeleteBuilder::class, JoinType::Inner, $from);
    }

    public function leftJoin(FromExp $from): JoinDeleteBuilder
    {
        return $this->addJoin(JoinDeleteBuilder::class, JoinType::Left, $from);
    }

    public function rightJoin(FromExp $from): JoinDeleteBuilder
    {
        return $this->addJoin(JoinDeleteBuilder::class, JoinType::Right, $from);
    }

    public function crossJoin(FromExp $from): JoinDeleteBuilder
    {
        return $this->addJoin(JoinDeleteBuilder::class, JoinType::Cross, $from);
    }

    /**
     * Add an ORDER BY expression (single-table delete only). Refine it via
     * {@see OrderByDeleteBuilder}.
     */
    public function orderBy(Exp $exp): OrderByDeleteBuilder
    {
        return $this->addOrderBy(OrderByDeleteBuilder::class, $exp);
    }

    /**
     * Add a RETURNING clause (single-table delete only). Refine the output name of
     * the last expression via {@see ReturningDeleteBuilder::as()}.
     */
    public function returning(Exp $outputExpression, Exp ...$exps): ReturningDeleteBuilder
    {
        $returningItems = $this->returningItems;
        foreach ([$outputExpression, ...array_values($exps)] as $exp) {
            $returningItems[] = new ReturningItem($exp);
        }

        return $this->derive(ReturningDeleteBuilder::class, returningItems: $returningItems);
    }
}
