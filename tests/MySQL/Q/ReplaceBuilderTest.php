<?php

declare(strict_types=1);

use Flowpack\QueryObjectBuilder\MySQL\Builder\QueryBuilderException;
use Flowpack\QueryObjectBuilder\MySQL\Q;

describe('MySQL REPLACE', function () {
    it('replaces a single row with column names', function () {
        expect(
            Q::replaceInto(Q::n('users'))
                ->columnNames('id', 'email')
                ->values(Q::arg(1), Q::arg('a@b.c')),
        )->toRenderSql('REPLACE INTO users (id,email) VALUES (?,?)', [1, 'a@b.c']);
    });

    it('replaces multiple rows', function () {
        expect(
            Q::replaceInto(Q::n('t'))
                ->columnNames('a', 'b')
                ->values(Q::int(1), Q::int(2))
                ->values(Q::int(3), Q::int(4)),
        )->toRenderSql('REPLACE INTO t (a,b) VALUES (1,2),(3,4)');
    });

    it('replaces from a map with stable column order', function () {
        expect(
            Q::replaceInto(Q::n('t'))->setMap(['b' => 2, 'a' => 1]),
        )->toRenderSql('REPLACE INTO t (a,b) VALUES (?,?)', [1, 2]);
    });

    it('uses the DEFAULT keyword as a value', function () {
        expect(
            Q::replaceInto(Q::n('t'))
                ->columnNames('id', 'created')
                ->values(Q::arg(1), Q::default()),
        )->toRenderSql('REPLACE INTO t (id,created) VALUES (?,DEFAULT)', [1]);
    });

    it('replaces with the result of a select query', function () {
        expect(
            Q::replaceInto(Q::n('archive'))
                ->columnNames('id', 'name')
                ->query(Q::select(Q::n('id'), Q::n('name'))->from(Q::n('users'))),
        )->toRenderSql('REPLACE INTO archive (id,name) SELECT id,name FROM users');
    });

    it('renders a default-values row', function () {
        expect(
            Q::replaceInto(Q::n('t'))->defaultValues(),
        )->toRenderSql('REPLACE INTO t () VALUES ()');
    });

    it('replaces values without column names', function () {
        expect(
            Q::replaceInto(Q::n('t'))->values(Q::int(1), Q::int(2)),
        )->toRenderSql('REPLACE INTO t VALUES (1,2)');
    });

    it('rejects setting both values and a query', function () {
        $q = Q::replaceInto(Q::n('t'))->columnNames('a')->values(Q::int(1))->query(Q::select(Q::int(2)));

        expect(static fn () => Q::build($q)->toSql())
            ->toThrow(QueryBuilderException::class, 'replace: cannot set both values and query');
    });
});
