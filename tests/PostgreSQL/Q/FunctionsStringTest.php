<?php

declare(strict_types=1);

use Flowpack\QueryObjectBuilder\PostgreSQL\Q;

describe('LetterCases', function () {
    it('renders lower', function () {
        $q = Q::select(Q\Func::lower(Q::n('a')))->from(Q::n('table'));

        expect($q)->toRenderSql('SELECT lower(a) FROM "table"', null);
    });

    it('renders lower with arg', function () {
        $q = Q::select(Q::n('id'))->from(Q::n('table'))
            ->where(Q\Func::lower(Q::n('name'))->eq(Q\Func::lower(Q::arg('foo'))));

        expect($q)->toRenderSql('SELECT id FROM "table" WHERE lower(name) = lower($1)', ['foo']);
    });

    it('renders upper', function () {
        $q = Q::select(Q\Func::upper(Q::n('a'))->eq(Q::arg('foo')))->from(Q::n('table'));

        expect($q)->toRenderSql('SELECT upper(a) = $1 FROM "table"', ['foo']);
    });

    it('renders initcap', function () {
        $q = Q::select(Q\Func::initcap(Q::n('a')))->from(Q::n('table'));

        expect($q)->toRenderSql('SELECT initcap(a) FROM "table"', null);
    });
});
