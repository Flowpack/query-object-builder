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
