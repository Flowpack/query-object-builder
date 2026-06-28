<?php

declare(strict_types=1);

use Flowpack\QueryObjectBuilder\PostgreSQL\Builder\QueryBuilderException;
use Flowpack\QueryObjectBuilder\PostgreSQL\Q;

describe('Case', function () {
    it('no expression', function () {
        $b = Q::case()
            ->when(Q::n('a')->eq(Q::int(1)))->then(Q::string('one'))
            ->end();

        // language=PostgreSQL
        expect($b)->toRenderSql(<<<'SQL'
            CASE WHEN a = 1 THEN 'one'
            END
            SQL, null);
    });

    it('with expression', function () {
        $b = Q::case(Q::n('a'))
            ->when(Q::int(1))->then(Q::string('one'))
            ->when(Q::int(2))->then(Q::string('two'))
            ->else(Q::string('other'))
            ->end();

        // language=PostgreSQL
        expect($b)->toRenderSql(<<<'SQL'
            CASE a
                WHEN 1 THEN 'one'
                WHEN 2 THEN 'two'
                ELSE 'other'
            END
            SQL, null);
    });

    it('add op', function () {
        $b = Q::case()
            ->when(Q::n('a')->eq(Q::int(1)))->then(Q::string('one'))
            ->end()->concat(Q::string('-dings'));

        // language=PostgreSQL
        expect($b)->toRenderSql(<<<'SQL'
            CASE WHEN a = 1 THEN 'one' END || '-dings'
            SQL, null);
    });

    it('errors with no when then', function () {
        $b = Q::case()->end();

        expect(static fn () => Q::build($b)->toSql())->toThrow(QueryBuilderException::class, 'case: no conditions given');
    });
});
