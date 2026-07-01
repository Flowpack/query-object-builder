<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MariaDB\Builder;

use Flowpack\QueryObjectBuilder\MySQL\Builder\AbstractSelectBuilder;
use Flowpack\QueryObjectBuilder\MySQL\Builder\CombinationType;
use Flowpack\QueryObjectBuilder\MySQL\Builder\Exp;
use Flowpack\QueryObjectBuilder\MySQL\Builder\FromExp;
use Flowpack\QueryObjectBuilder\MySQL\Builder\JoinType;
use Flowpack\QueryObjectBuilder\MySQL\Builder\LockingClause;

/**
 * Builds a MariaDB SELECT query.
 *
 * The shared state, rendering and clause logic live in {@see AbstractSelectBuilder};
 * this concrete builder adds MariaDB's public transition methods and renders the
 * shared lock as `LOCK IN SHARE MODE`. There is no `LATERAL` from/join family.
 */
class SelectBuilder extends AbstractSelectBuilder
{
    /**
     * Add the given expressions to the select list.
     */
    public function select(Exp ...$exps): SelectSelectBuilder
    {
        return $this->addToSelectList(SelectSelectBuilder::class, array_values($exps));
    }

    /**
     * Add a table / subquery to the FROM clause.
     */
    public function from(FromExp $from): FromSelectBuilder
    {
        return $this->addFromItem(FromSelectBuilder::class, $from);
    }

    public function join(FromExp $from): JoinSelectBuilder
    {
        return $this->addJoinItem(JoinSelectBuilder::class, JoinType::Inner, $from, false);
    }

    public function leftJoin(FromExp $from): JoinSelectBuilder
    {
        return $this->addJoinItem(JoinSelectBuilder::class, JoinType::Left, $from, false);
    }

    public function rightJoin(FromExp $from): JoinSelectBuilder
    {
        return $this->addJoinItem(JoinSelectBuilder::class, JoinType::Right, $from, false);
    }

    public function crossJoin(FromExp $from): JoinSelectBuilder
    {
        return $this->addJoinItem(JoinSelectBuilder::class, JoinType::Cross, $from, false);
    }

    /**
     * Add a WHERE condition. Multiple calls are joined with AND.
     */
    public function where(Exp $cond): SelectBuilder
    {
        return $this->addWhereCondition(SelectBuilder::class, $cond);
    }

    /**
     * Add expressions to the GROUP BY clause. Refine with
     * {@see GroupBySelectBuilder::withRollup()}.
     */
    public function groupBy(Exp ...$exps): GroupBySelectBuilder
    {
        return $this->addGroupByExps(GroupBySelectBuilder::class, array_values($exps));
    }

    /**
     * Add a HAVING condition. Multiple calls are joined with AND.
     */
    public function having(Exp $cond): SelectBuilder
    {
        return $this->addHavingCondition(SelectBuilder::class, $cond);
    }

    /**
     * Add a named window to the WINDOW clause.
     */
    public function window(string $name): WindowSelectBuilder
    {
        return $this->addNamedWindow(WindowSelectBuilder::class, $name);
    }

    /**
     * Add an ORDER BY expression (refine it via {@see OrderBySelectBuilder}).
     */
    public function orderBy(Exp $exp): OrderBySelectBuilder
    {
        return $this->addOrderByExp(OrderBySelectBuilder::class, $exp);
    }

    public function limit(Exp $exp): SelectBuilder
    {
        return $this->withLimit(SelectBuilder::class, $exp);
    }

    public function offset(Exp $exp): SelectBuilder
    {
        return $this->withOffset(SelectBuilder::class, $exp);
    }

    /**
     * Combine this select with the following one using UNION. Refine with
     * {@see CombinationBuilder::all()} or supply the query via
     * {@see CombinationBuilder::query()}.
     */
    public function union(): CombinationBuilder
    {
        return $this->startCombination(CombinationBuilder::class, CombinationType::Union);
    }

    public function intersect(): CombinationBuilder
    {
        return $this->startCombination(CombinationBuilder::class, CombinationType::Intersect);
    }

    public function except(): CombinationBuilder
    {
        return $this->startCombination(CombinationBuilder::class, CombinationType::Except);
    }

    public function forUpdate(): ForSelectBuilder
    {
        return $this->withLocking(ForSelectBuilder::class, new LockingClause('FOR UPDATE'));
    }

    /**
     * Lock the selected rows for sharing (`LOCK IN SHARE MODE`).
     */
    public function forShare(): SelectBuilder
    {
        return $this->withLocking(SelectBuilder::class, new LockingClause('LOCK IN SHARE MODE'));
    }

    /**
     * Append the given WITH queries to this select's WITH clause.
     */
    public function appendWith(WithBuilder $with): SelectBuilder
    {
        return $this->withAppendedWith(SelectBuilder::class, $with->withQueryItems());
    }
}
