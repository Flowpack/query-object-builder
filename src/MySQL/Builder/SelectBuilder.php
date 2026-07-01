<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * Builds a MySQL SELECT query.
 *
 * The shared state, rendering and clause logic live in {@see AbstractSelectBuilder};
 * this concrete builder adds MySQL's public transition methods (including the
 * `LATERAL` from/join family) and renders the shared lock as `FOR SHARE`.
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

    /**
     * Add a `LATERAL` subquery to the FROM clause.
     */
    public function fromLateral(FromLateralExp $from): FromSelectBuilder
    {
        return $this->addFromItem(FromSelectBuilder::class, $from, true);
    }

    public function join(FromExp $from): JoinSelectBuilder
    {
        return $this->addJoinItem(JoinSelectBuilder::class, JoinType::Inner, $from, false);
    }

    public function joinLateral(FromExp $from): JoinSelectBuilder
    {
        return $this->addJoinItem(JoinSelectBuilder::class, JoinType::Inner, $from, true);
    }

    public function leftJoin(FromExp $from): JoinSelectBuilder
    {
        return $this->addJoinItem(JoinSelectBuilder::class, JoinType::Left, $from, false);
    }

    public function leftJoinLateral(FromExp $from): JoinSelectBuilder
    {
        return $this->addJoinItem(JoinSelectBuilder::class, JoinType::Left, $from, true);
    }

    public function rightJoin(FromExp $from): JoinSelectBuilder
    {
        return $this->addJoinItem(JoinSelectBuilder::class, JoinType::Right, $from, false);
    }

    public function crossJoin(FromExp $from): JoinSelectBuilder
    {
        return $this->addJoinItem(JoinSelectBuilder::class, JoinType::Cross, $from, false);
    }

    public function crossJoinLateral(FromExp $from): JoinSelectBuilder
    {
        return $this->addJoinItem(JoinSelectBuilder::class, JoinType::Cross, $from, true);
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
     * Add a named window to the WINDOW clause. Define it via
     * {@see WindowSelectBuilder::as()} / {@see WindowSelectBuilder::partitionBy()} /
     * {@see WindowDefining::orderBy()}, then reference it from a window function
     * via {@see WindowFuncBuilder::over()}.
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

    public function forShare(): ForSelectBuilder
    {
        return $this->withLocking(ForSelectBuilder::class, new LockingClause('FOR SHARE'));
    }

    /**
     * Append the given WITH queries to this select's WITH clause.
     */
    public function appendWith(WithBuilder $with): SelectBuilder
    {
        return $this->withAppendedWith(SelectBuilder::class, $with->withQueryItems());
    }
}
