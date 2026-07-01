<?php

declare(strict_types=1);

use Flowpack\QueryObjectBuilder\MySQL\Builder\QueryBuilderException;
use Flowpack\QueryObjectBuilder\MySQL\Q;

describe('MySQL DELETE', function () {
    it('deletes with a WHERE condition', function () {
        expect(
            Q::deleteFrom(Q::n('users'))->where(Q::n('id')->eq(Q::arg(1))),
        )->toRenderSql('DELETE FROM users WHERE id = ?', [1]);
    });

    it('aliases the target table', function () {
        expect(
            Q::deleteFrom(Q::n('users'))->as('u')->where(Q::n('u.id')->eq(Q::arg(1))),
        )->toRenderSql('DELETE FROM users AS u WHERE u.id = ?', [1]);
    });

    it('renders ORDER BY and LIMIT on a single-table delete', function () {
        expect(
            Q::deleteFrom(Q::n('somelog'))
                ->where(Q::n('user')->eq(Q::string('jcole')))
                ->orderBy(Q::n('timestamp_column'))
                ->limit(Q::int(1)),
        )->toRenderSql("DELETE FROM somelog WHERE user = 'jcole' ORDER BY timestamp_column LIMIT 1");
    });

    it('orders a single-table delete ascending and descending', function () {
        expect(
            Q::deleteFrom(Q::n('somelog'))->orderBy(Q::n('ts'))->desc()->limit(Q::int(1)),
        )->toRenderSql('DELETE FROM somelog ORDER BY ts DESC LIMIT 1');

        expect(
            Q::deleteFrom(Q::n('somelog'))->orderBy(Q::n('ts'))->asc()->limit(Q::int(1)),
        )->toRenderSql('DELETE FROM somelog ORDER BY ts ASC LIMIT 1');
    });

    it('orders a single-table delete by multiple columns', function () {
        expect(
            Q::deleteFrom(Q::n('somelog'))->orderBy(Q::n('a'))->orderBy(Q::n('b'))->desc()->limit(Q::int(1)),
        )->toRenderSql('DELETE FROM somelog ORDER BY a,b DESC LIMIT 1');
    });

    it('renders a multi-table delete with a LEFT JOIN', function () {
        expect(
            Q::deleteFrom(Q::n('t1'))
                ->leftJoin(Q::n('t2'))->on(Q::n('t1.id')->eq(Q::n('t2.id')))
                ->where(Q::n('t2.id')->isNull()),
        )->toRenderSql('DELETE t1.* FROM t1 LEFT JOIN t2 ON t1.id = t2.id WHERE t2.id IS NULL');
    });

    it('renders a multi-table delete with aliased targets', function () {
        expect(
            Q::deleteFrom(Q::n('t1'))->as('a1')
                ->join(Q::n('t2'))->as('a2')
                ->where(Q::n('a1.id')->eq(Q::n('a2.id'))),
        )->toRenderSql('DELETE a1.* FROM t1 AS a1 JOIN t2 AS a2 WHERE a1.id = a2.id');
    });

    it('renders a multi-table delete with RIGHT and CROSS JOIN', function () {
        expect(
            Q::deleteFrom(Q::n('t1'))
                ->rightJoin(Q::n('t2'))->on(Q::n('t1.id')->eq(Q::n('t2.id')))
                ->where(Q::n('t2.active')->eq(Q::int(0))),
        )->toRenderSql('DELETE t1.* FROM t1 RIGHT JOIN t2 ON t1.id = t2.id WHERE t2.active = 0');

        expect(
            Q::deleteFrom(Q::n('t1'))
                ->crossJoin(Q::n('t2'))
                ->where(Q::n('t1.id')->eq(Q::n('t2.id'))),
        )->toRenderSql('DELETE t1.* FROM t1 CROSS JOIN t2 WHERE t1.id = t2.id');
    });

    it('renders a multi-table delete joining USING', function () {
        expect(
            Q::deleteFrom(Q::n('t1'))
                ->join(Q::n('t2'))->using('id')
                ->where(Q::n('t2.active')->eq(Q::int(0))),
        )->toRenderSql('DELETE t1.* FROM t1 JOIN t2 USING (id) WHERE t2.active = 0');
    });

    it('rejects ORDER BY / LIMIT on a multi-table delete', function () {
        $q = Q::deleteFrom(Q::n('t1'))
            ->join(Q::n('t2'))->on(Q::n('t1.id')->eq(Q::n('t2.id')))
            ->limit(Q::int(1));

        expect(static fn () => Q::build($q)->toSql())->toThrow(QueryBuilderException::class);
    });

    it('renders a leading WITH clause', function () {
        expect(
            Q::with('stale')->as(Q::select(Q::n('id'))->from(Q::n('sessions'))->where(Q::n('expired')->eq(Q::int(1))))
                ->deleteFrom(Q::n('users'))
                ->where(Q::n('id')->in(Q::select(Q::n('id'))->from(Q::n('stale')))),
        )->toRenderSql(
            'WITH stale AS (SELECT id FROM sessions WHERE expired = 1) DELETE FROM users WHERE id IN (SELECT id FROM stale)',
        );
    });
});
