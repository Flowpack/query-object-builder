<?php

declare(strict_types=1);

use Flowpack\QueryObjectBuilder\PostgreSQL\Builder\QueryBuilderException;
use Flowpack\QueryObjectBuilder\PostgreSQL\Q;

describe('Bind', function () {
    it('binds a single named arg', function () {
        $q = Q::select(Q::n('*'))->from(Q::n('employees'))->where(Q::n('id')->eq(Q::bind('id')));

        [$sql, $args] = Q::build($q)->withNamedArgs(['id' => 42])->toSql();

        expect($sql)->toBe('SELECT * FROM employees WHERE id = $1');
        expect($args)->toBe([42]);
    });

    it('re-uses a named arg', function () {
        $q = Q::select(Q::n('*'))
            ->from(Q::n('employees'))
            ->where(Q::or(
                Q::n('firstname')->ilike(Q::bind('search')),
                Q::n('lastname')->ilike(Q::bind('search')),
            ));

        [$sql, $args] = Q::build($q)->withNamedArgs(['search' => 'Jo%'])->toSql();

        expect($sql)->toBe('SELECT * FROM employees WHERE firstname ILIKE $1 OR lastname ILIKE $1');
        expect($args)->toBe(['Jo%']);
    });

    it('binds multiple named args', function () {
        $q = Q::select(Q::n('*'))
            ->from(Q::n('employees'))
            ->where(Q::and(
                Q::or(
                    Q::n('firstname')->ilike(Q::bind('search')),
                    Q::n('lastname')->ilike(Q::bind('search')),
                ),
                Q::n('active')->eq(Q::bind('active')),
            ));

        [$sql, $args] = Q::build($q)->withNamedArgs(['search' => 'Jo%', 'active' => true])->toSql();

        expect($sql)->toBe('SELECT * FROM employees WHERE (firstname ILIKE $1 OR lastname ILIKE $1) AND active = $2');
        expect($args)->toBe(['Jo%', true]);
    });

    it('errors on a missing named arg', function () {
        $q = Q::select(Q::n('*'))->from(Q::n('employees'))->where(Q::n('id')->eq(Q::bind('id')));

        expect(static fn () => Q::build($q)->toSql())->toThrow(QueryBuilderException::class, 'missing named argument "id"');
    });

    it('mixes Arg and Bind', function () {
        $q = Q::select(Q::n('*'))
            ->from(Q::n('employees'))
            ->where(Q::and(
                Q::or(
                    Q::n('firstname')->ilike(Q::bind('search')),
                    Q::n('lastname')->ilike(Q::bind('search')),
                ),
                Q::n('active')->eq(Q::arg(true)),
            ));

        [$sql, $args] = Q::build($q)->withNamedArgs(['search' => 'Jo%'])->toSql();

        expect($sql)->toBe('SELECT * FROM employees WHERE (firstname ILIKE $1 OR lastname ILIKE $1) AND active = $2');
        expect($args)->toBe(['Jo%', true]);
    });
});
