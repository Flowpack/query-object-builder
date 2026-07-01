<?php

declare(strict_types=1);

use Flowpack\QueryObjectBuilder\MariaDB\Q;
use Flowpack\QueryObjectBuilder\MySQL\Builder\QueryBuilderException;

describe('MariaDB INSERT', function () {
    it('inserts a single row', function () {
        expect(
            Q::insertInto(Q::n('users'))->columnNames('id', 'email')->values(Q::arg(1), Q::arg('a@b.c')),
        )->toRenderSql('INSERT INTO users (id,email) VALUES (?,?)', [1, 'a@b.c']);
    });

    it('renders ON DUPLICATE KEY UPDATE with the VALUES() reference (no row alias)', function () {
        expect(
            Q::insertInto(Q::n('t'))
                ->columnNames('id', 'hits')
                ->values(Q::arg(1), Q::arg(10))
                ->onDuplicateKeyUpdate()
                ->set('hits', Q::inserted('hits'))
                ->set('seen', Q::int(1)),
        )->toRenderSql(
            'INSERT INTO t (id,hits) VALUES (?,?) ON DUPLICATE KEY UPDATE hits = VALUES(hits),seen = 1',
            [1, 10],
        );
    });

    it('renders a RETURNING clause', function () {
        expect(
            Q::insertInto(Q::n('t'))->columnNames('a')->values(Q::arg(1))
                ->returning(Q::n('id'))->as('new_id'),
        )->toRenderSql('INSERT INTO t (a) VALUES (?) RETURNING id AS new_id', [1]);
    });
});

describe('MariaDB REPLACE', function () {
    it('renders a RETURNING clause', function () {
        expect(
            Q::replaceInto(Q::n('t'))->columnNames('a')->values(Q::arg(1))
                ->returning(Q::n('id')),
        )->toRenderSql('REPLACE INTO t (a) VALUES (?) RETURNING id', [1]);
    });
});

describe('MariaDB UPDATE', function () {
    it('reuses the shared multi-table update', function () {
        expect(
            Q::update(Q::n('t1'))
                ->join(Q::n('t2'))->on(Q::n('t1.id')->eq(Q::n('t2.id')))
                ->set('t1.a', Q::n('t2.b')),
        )->toRenderSql('UPDATE t1 JOIN t2 ON t1.id = t2.id SET t1.a = t2.b');
    });
});

describe('MariaDB DELETE', function () {
    it('deletes with RETURNING (single-table)', function () {
        expect(
            Q::deleteFrom(Q::n('t'))->where(Q::n('id')->eq(Q::arg(1)))->returning(Q::n('id')),
        )->toRenderSql('DELETE FROM t WHERE id = ? RETURNING id', [1]);
    });

    it('renders a multi-table delete', function () {
        expect(
            Q::deleteFrom(Q::n('t1'))
                ->leftJoin(Q::n('t2'))->on(Q::n('t1.id')->eq(Q::n('t2.id')))
                ->where(Q::n('t2.id')->isNull()),
        )->toRenderSql('DELETE t1.* FROM t1 LEFT JOIN t2 ON t1.id = t2.id WHERE t2.id IS NULL');
    });

    it('rejects RETURNING on a multi-table delete', function () {
        $q = Q::deleteFrom(Q::n('t1'))
            ->join(Q::n('t2'))->on(Q::n('t1.id')->eq(Q::n('t2.id')))
            ->returning(Q::n('t1.id'));

        expect(static fn () => Q::build($q)->toSql())->toThrow(QueryBuilderException::class);
    });
});
