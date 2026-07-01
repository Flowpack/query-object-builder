<?php

declare(strict_types=1);

use Flowpack\QueryObjectBuilder\MariaDB\Q;

describe('MariaDB SELECT', function () {
    it('renders a basic select with where and bound args', function () {
        expect(
            Q::select(Q::n('id'), Q::n('email'))
                ->from(Q::n('orders'))
                ->where(Q::n('id')->eq(Q::arg(1)))
        )->toRenderSql('SELECT id,email FROM orders WHERE id = ?', [1]);

        // A reserved keyword used as an identifier is backtick-quoted.
        expect(Q::select(Q::n('id'))->from(Q::n('order')))
            ->toRenderSql('SELECT id FROM `order`');
    });

    it('aliases select expressions and from items', function () {
        expect(
            Q::select(Q::n('u.id'))->as('user_id')
                ->from(Q::n('users'))->as('u')
        )->toRenderSql('SELECT u.id AS user_id FROM users AS u');
    });

    it('renders DISTINCT', function () {
        expect(
            Q::select(Q::n('country'))->distinct()->from(Q::n('users'))
        )->toRenderSql('SELECT DISTINCT country FROM users');
    });

    it('renders joins with ON and USING', function () {
        expect(
            Q::select(Q::n('*'))
                ->from(Q::n('users'))->as('u')
                ->leftJoin(Q::n('orders'))->as('o')->on(Q::n('o.user_id')->eq(Q::n('u.id')))
        )->toRenderSql('SELECT * FROM users AS u LEFT JOIN orders AS o ON o.user_id = u.id');

        expect(
            Q::select(Q::n('*'))->from(Q::n('a'))->join(Q::n('b'))->using('id')
        )->toRenderSql('SELECT * FROM a JOIN b USING (id)');
    });

    it('renders GROUP BY with rollup, HAVING and ORDER BY', function () {
        expect(
            Q::select(Q::n('country'))
                ->from(Q::n('users'))
                ->groupBy(Q::n('country'))->withRollup()
                ->having(Q::n('country')->isNotNull())
                ->orderBy(Q::n('country'))->desc()
        )->toRenderSql('SELECT country FROM users GROUP BY country WITH ROLLUP HAVING country IS NOT NULL ORDER BY country DESC');
    });

    it('renders LIMIT and OFFSET', function () {
        expect(
            Q::select(Q::n('id'))->from(Q::n('users'))->limit(Q::int(10))->offset(Q::int(20))
        )->toRenderSql('SELECT id FROM users LIMIT 10 OFFSET 20');
    });

    it('renders locking clauses', function () {
        expect(
            Q::select(Q::n('id'))->from(Q::n('users'))->forUpdate()->skipLocked()
        )->toRenderSql('SELECT id FROM users FOR UPDATE SKIP LOCKED');

        // MariaDB spells the shared lock as LOCK IN SHARE MODE.
        expect(
            Q::select(Q::n('id'))->from(Q::n('users'))->forShare()
        )->toRenderSql('SELECT id FROM users LOCK IN SHARE MODE');
    });

    it('renders UNION and INTERSECT combinations', function () {
        expect(
            Q::select(Q::n('id'))->from(Q::n('a'))
                ->union()->all()
                ->query(Q::select(Q::n('id'))->from(Q::n('b')))
        )->toRenderSql('SELECT id FROM a UNION ALL (SELECT id FROM b)');
    });

    it('renders a CTE', function () {
        expect(
            Q::with('recent')->as(Q::select(Q::n('id'))->from(Q::n('orders')))
                ->select(Q::n('*'))->from(Q::n('recent'))
        )->toRenderSql('WITH recent AS (SELECT id FROM orders) SELECT * FROM recent');
    });

    it('renders EXISTS and IN with a subquery', function () {
        expect(
            Q::select(Q::n('id'))->from(Q::n('users'))
                ->where(Q::exists(Q::select(Q::int(1))->from(Q::n('orders'))))
        )->toRenderSql('SELECT id FROM users WHERE EXISTS (SELECT 1 FROM orders)');

        expect(
            Q::select(Q::n('id'))->from(Q::n('users'))
                ->where(Q::n('id')->in(Q::select(Q::n('user_id'))->from(Q::n('orders'))))
        )->toRenderSql('SELECT id FROM users WHERE id IN (SELECT user_id FROM orders)');
    });

    it('renders a named WINDOW clause with a frame', function () {
        expect(
            Q::select(Q::n('val'))
                ->from(Q::n('t'))
                ->window('w')->as()->orderBy(Q::n('val'))->rows(Q::unboundedPreceding())
        )->toRenderSql('SELECT val FROM t WINDOW w AS (ORDER BY val ROWS UNBOUNDED PRECEDING)');
    });
});
