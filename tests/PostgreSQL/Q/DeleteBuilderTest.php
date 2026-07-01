<?php

declare(strict_types=1);

use Flowpack\QueryObjectBuilder\PostgreSQL\Q;

describe('DeleteBuilder', function () {
    describe('examples', function () {
        it('renders example 0.1', function () {
            $q = Q::deleteFrom(Q::n('films'))
                ->using(Q::n('producers'))
                ->where(Q::and(
                    Q::n('producer_id')->eq(Q::n('producers.id')),
                    Q::n('producers.name')->eq(Q::string('foo')),
                ));

            expect($q)->toRenderSql(
                <<<'SQL'
                DELETE FROM films USING producers
                  WHERE producer_id = producers.id AND producers.name = 'foo'
                SQL,
                null,
            );
        });

        it('renders example 0.2', function () {
            $q = Q::deleteFrom(Q::n('films'))
                ->where(Q::n('producer_id')->in(
                    Q::select(Q::n('id'))->from(Q::n('producers'))->where(Q::n('name')->eq(Q::string('foo'))),
                ));

            expect($q)->toRenderSql(
                <<<'SQL'
                DELETE FROM films
                  WHERE producer_id IN (SELECT id FROM producers WHERE name = 'foo')
                SQL,
                null,
            );
        });

        it('renders example 1.1', function () {
            $q = Q::deleteFrom(Q::n('films'))
                ->where(Q::n('kind')->neq(Q::string('Musical')));

            expect($q)->toRenderSql("DELETE FROM films WHERE kind <> 'Musical'", null);
        });

        it('renders example 1.2', function () {
            $q = Q::deleteFrom(Q::n('films'));

            expect($q)->toRenderSql('DELETE FROM films', null);
        });

        it('renders example 1.3', function () {
            $q = Q::deleteFrom(Q::n('tasks'))
                ->where(Q::n('status')->eq(Q::string('DONE')))
                ->returning(Q::n('*'));

            expect($q)->toRenderSql("DELETE FROM tasks WHERE status = 'DONE' RETURNING *", null);
        });
    });

    it('aliases the target table', function () {
        $q = Q::deleteFrom(Q::n('films'))->as('f')->where(Q::n('f.kind')->eq(Q::string('Musical')));

        expect($q)->toRenderSql("DELETE FROM films AS f WHERE f.kind = 'Musical'", null);
    });

    it('aliases a USING item and its columns', function () {
        $q = Q::deleteFrom(Q::n('films'))
            ->using(Q::select(Q::n('id'), Q::n('name'))->from(Q::n('producers')))->as('p')->columnAliases('pid', 'pname')
            ->where(Q::n('films.producer_id')->eq(Q::n('p.pid')));

        expect($q)->toRenderSql(
            'DELETE FROM films USING (SELECT id,name FROM producers) AS p (pid,pname) WHERE films.producer_id = p.pid',
            null,
        );
    });

    it('lists multiple USING items', function () {
        $q = Q::deleteFrom(Q::n('films'))
            ->using(Q::n('producers'))
            ->using(Q::n('studios'))
            ->where(Q::n('films.producer_id')->eq(Q::n('producers.id')));

        expect($q)->toRenderSql(
            'DELETE FROM films USING producers,studios WHERE films.producer_id = producers.id',
            null,
        );
    });

    it('returns an aliased column', function () {
        $q = Q::deleteFrom(Q::n('tasks'))->where(Q::n('status')->eq(Q::string('DONE')))
            ->returning(Q::n('id'))->as('task_id');

        expect($q)->toRenderSql("DELETE FROM tasks WHERE status = 'DONE' RETURNING id AS task_id", null);
    });

    it('renders with', function () {
        $listens = Q::n('listens');

        $q = Q::with('max_table')->as(
            Q::select(Q::n('uid'))->select(Q\Func::max(Q::n('ts'))->minus(Q::int(10000)))->as('mx')->from(Q::n('listens'))->groupBy(Q::n('uid')),
        )
            ->deleteFrom($listens)
            ->where(Q::n('ts')->lt(Q::select(Q::n('mx'))->from(Q::n('max_table'))->where(Q::n('max_table.uid')->eq(Q::n('listens.uid')))));

        expect($q)->toRenderSql(
            <<<'SQL'
            WITH max_table AS (
                SELECT uid, max(ts) - 10000 AS mx
                FROM listens
                GROUP BY uid
            )
            DELETE FROM listens
            WHERE ts < (SELECT mx
                               FROM max_table
                               WHERE max_table.uid = listens.uid)
            SQL,
            null,
        );
    });

    it('renders row-constructor IN with unnest args', function () {
        $q = Q::deleteFrom(Q::n('list_line_items'))
            ->where(Q::and(
                Q::n('shopping_cart_id')->eq(Q::arg(99)),
                Q::exps(Q::n('supplier_id'), Q::n('article_id'), Q::n('unit_id'))->in(
                    Q::select(
                        Q\Func::unnest(Q::arg([1, 2, 3])->cast('integer[]')),
                        Q\Func::unnest(Q::arg([4, 5, 6])->cast('integer[]')),
                        Q\Func::unnest(Q::arg([7, 8, 9])->cast('integer[]')),
                    ),
                ),
            ));

        expect($q)->toRenderSql(
            <<<'SQL'
            DELETE FROM list_line_items
            WHERE shopping_cart_id = $1
            AND (supplier_id, article_id, unit_id) IN (
                SELECT unnest($2::integer[]), unnest($3::integer[]), unnest($4::integer[])
            )
            SQL,
            [99, [1, 2, 3], [4, 5, 6], [7, 8, 9]],
        );
    });
});
