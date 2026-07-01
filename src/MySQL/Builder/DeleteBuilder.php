<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * Builds a MySQL DELETE statement (single-table or, via {@see join()}, multi-table
 * `DELETE tbl.* FROM <refs>`).
 */
class DeleteBuilder extends AbstractDeleteBuilder
{
    /**
     * Join another table, turning this into a multi-table delete. Refine it via
     * {@see JoinDeleteBuilder::on()} / {@see JoinDeleteBuilder::using()} /
     * {@see JoinDeleteBuilder::as()}.
     */
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
}
