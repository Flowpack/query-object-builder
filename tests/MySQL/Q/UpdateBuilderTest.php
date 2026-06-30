<?php

declare(strict_types=1);

use Flowpack\QueryObjectBuilder\MySQL\Builder\QueryBuilderException;
use Flowpack\QueryObjectBuilder\MySQL\Q;

describe('MySQL UPDATE', function () {
    it('updates with SET and WHERE', function () {
        expect(
            Q::update(Q::n('users'))
                ->set('name', Q::arg('Jane'))
                ->where(Q::n('id')->eq(Q::arg(1))),
        )->toRenderSql('UPDATE users SET name = ? WHERE id = ?', ['Jane', 1]);
    });

    it('updates from a map with stable column order', function () {
        expect(
            Q::update(Q::n('t'))->setMap(['b' => 2, 'a' => 1]),
        )->toRenderSql('UPDATE t SET a = ?,b = ?', [1, 2]);
    });

    it('quotes reserved keyword columns in SET', function () {
        expect(
            Q::update(Q::n('t'))->set('order', Q::int(1)),
        )->toRenderSql('UPDATE t SET `order` = 1');
    });

    it('aliases the target table', function () {
        expect(
            Q::update(Q::n('users'))->as('u')->set('u.name', Q::arg('x')),
        )->toRenderSql('UPDATE users AS u SET u.name = ?', ['x']);
    });

    it('renders ORDER BY and LIMIT on a single-table update', function () {
        expect(
            Q::update(Q::n('t'))
                ->set('id', Q::n('id')->plus(Q::int(1)))
                ->orderBy(Q::n('id'))->desc()
                ->limit(Q::int(10)),
        )->toRenderSql('UPDATE t SET id = id + 1 ORDER BY id DESC LIMIT 10');
    });

    it('renders a multi-table update with a LEFT JOIN', function () {
        expect(
            Q::update(Q::n('t1'))
                ->leftJoin(Q::n('t2'))->on(Q::n('t1.id')->eq(Q::n('t2.id')))
                ->set('t1.col1', Q::n('t2.col1'))
                ->where(Q::n('t2.col2')->isNull()),
        )->toRenderSql(
            'UPDATE t1 LEFT JOIN t2 ON t1.id = t2.id SET t1.col1 = t2.col1 WHERE t2.col2 IS NULL',
        );
    });

    it('renders a multi-table update joining on equal ids', function () {
        expect(
            Q::update(Q::n('items'))
                ->join(Q::n('month'))->on(Q::n('items.id')->eq(Q::n('month.id')))
                ->set('items.price', Q::n('month.price')),
        )->toRenderSql('UPDATE items JOIN month ON items.id = month.id SET items.price = month.price');
    });

    it('rejects ORDER BY / LIMIT on a multi-table update', function () {
        $q = Q::update(Q::n('t1'))
            ->join(Q::n('t2'))->on(Q::n('t1.id')->eq(Q::n('t2.id')))
            ->set('t1.a', Q::int(1))
            ->limit(Q::int(1));

        expect(static fn () => Q::build($q)->toSql())->toThrow(QueryBuilderException::class);
    });

    it('renders a leading WITH clause', function () {
        expect(
            Q::with('ids')->as(Q::select(Q::n('id'))->from(Q::n('flagged')))
                ->update(Q::n('users'))
                ->set('active', Q::int(0))
                ->where(Q::n('id')->in(Q::select(Q::n('id'))->from(Q::n('ids')))),
        )->toRenderSql(
            'WITH ids AS (SELECT id FROM flagged) UPDATE users SET active = 0 WHERE id IN (SELECT id FROM ids)',
        );
    });

    it('applies a conditional modification with applyIf', function () {
        $build = static fn (bool $withLimit) => Q::update(Q::n('t'))
            ->set('a', Q::int(1))
            ->applyIf($withLimit, static fn ($b) => $b->limit(Q::int(5)));

        expect($build(true))->toRenderSql('UPDATE t SET a = 1 LIMIT 5');
        expect($build(false))->toRenderSql('UPDATE t SET a = 1');
    });
});
