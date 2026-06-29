<?php

declare(strict_types=1);

use Flowpack\QueryObjectBuilder\PostgreSQL\Builder\QueryBuilderException;
use Flowpack\QueryObjectBuilder\PostgreSQL\Q;

describe('QueryBuilder', function () {
    it('errors on an invalid identifier when validating', function () {
        $q = Q::select(Q::int(1))->from(Q::n('1foo'));

        expect(static fn () => Q::build($q)->toSql())->toThrow(QueryBuilderException::class);
    });

    it('builds without validation', function () {
        $q = Q::select(Q::int(1))->from(Q::n('1foo'));

        [$sql, $args] = Q::build($q)->withoutValidation()->toSql();

        expect($sql)->toBe('SELECT 1 FROM 1foo');
        expect($args)->toBeEmpty();
    });
});
