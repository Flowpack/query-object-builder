<?php

declare(strict_types=1);

use Flowpack\QueryObjectBuilder\MySQL\Builder\QueryBuilderException;
use Flowpack\QueryObjectBuilder\MySQL\Q;

describe('MySQL INSERT', function () {
    it('inserts a single row with column names', function () {
        expect(
            Q::insertInto(Q::n('users'))
                ->columnNames('id', 'email')
                ->values(Q::arg(1), Q::arg('a@b.c')),
        )->toRenderSql('INSERT INTO users (id,email) VALUES (?,?)', [1, 'a@b.c']);
    });

    it('inserts multiple rows', function () {
        expect(
            Q::insertInto(Q::n('t'))
                ->columnNames('a', 'b')
                ->values(Q::int(1), Q::int(2))
                ->values(Q::int(3), Q::int(4)),
        )->toRenderSql('INSERT INTO t (a,b) VALUES (1,2),(3,4)');
    });

    it('backtick-quotes reserved keyword columns', function () {
        expect(
            Q::insertInto(Q::n('t'))
                ->columnNames('id', 'order')
                ->values(Q::int(1), Q::int(2)),
        )->toRenderSql('INSERT INTO t (id,`order`) VALUES (1,2)');
    });

    it('inserts from a map with stable column order', function () {
        expect(
            Q::insertInto(Q::n('t'))->setMap(['b' => 2, 'a' => 1]),
        )->toRenderSql('INSERT INTO t (a,b) VALUES (?,?)', [1, 2]);
    });

    it('inserts the result of a select query', function () {
        expect(
            Q::insertInto(Q::n('archive'))
                ->columnNames('id', 'name')
                ->query(
                    Q::select(Q::n('id'), Q::n('name'))->from(Q::n('users'))->where(Q::n('active')->eq(Q::int(0))),
                ),
        )->toRenderSql('INSERT INTO archive (id,name) SELECT id,name FROM users WHERE active = 0');
    });

    it('renders INSERT IGNORE', function () {
        expect(
            Q::insertInto(Q::n('t'))->ignore()->columnNames('a')->values(Q::int(1)),
        )->toRenderSql('INSERT IGNORE INTO t (a) VALUES (1)');
    });

    it('renders a default-values row', function () {
        expect(
            Q::insertInto(Q::n('t'))->defaultValues(),
        )->toRenderSql('INSERT INTO t () VALUES ()');
    });

    it('inserts values without column names', function () {
        expect(
            Q::insertInto(Q::n('t'))->values(Q::int(1), Q::int(2)),
        )->toRenderSql('INSERT INTO t VALUES (1,2)');
    });

    it('rejects setting both values and a query', function () {
        $q = Q::insertInto(Q::n('t'))->columnNames('a')->values(Q::int(1))->query(Q::select(Q::int(2)));

        expect(static fn () => Q::build($q)->toSql())
            ->toThrow(QueryBuilderException::class, 'insert: cannot set both values and query');
    });

    it('renders ON DUPLICATE KEY UPDATE with the AS new row alias', function () {
        expect(
            Q::insertInto(Q::n('t'))
                ->columnNames('id', 'hits')
                ->values(Q::arg(1), Q::arg(10))->as('new')
                ->onDuplicateKeyUpdate()
                ->set('hits', Q::n('new.hits'))
                ->set('seen', Q::int(1)),
        )->toRenderSql(
            'INSERT INTO t (id,hits) VALUES (?,?) AS new ON DUPLICATE KEY UPDATE hits = new.hits,seen = 1',
            [1, 10],
        );
    });

    it('renders ON DUPLICATE KEY UPDATE with the portable VALUES() reference', function () {
        expect(
            Q::insertInto(Q::n('t'))
                ->columnNames('id', 'hits')
                ->values(Q::arg(1), Q::arg(10))
                ->onDuplicateKeyUpdate()
                ->set('hits', Q::values('hits')),
        )->toRenderSql(
            'INSERT INTO t (id,hits) VALUES (?,?) ON DUPLICATE KEY UPDATE hits = VALUES(hits)',
            [1, 10],
        );
    });
});
