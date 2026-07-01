<?php

declare(strict_types=1);

use Flowpack\QueryObjectBuilder\MySQL\Builder\QueryBuilderException;
use Flowpack\QueryObjectBuilder\MySQL\Q;

describe('MySQL named args', function () {
    it('binds a single named arg', function () {
        $q = Q::select(Q::n('*'))->from(Q::n('employees'))->where(Q::n('id')->eq(Q::bind('id')));

        [$sql, $args] = Q::build($q)->withNamedArgs(['id' => 42])->toSql();

        expect($sql)->toBe('SELECT * FROM employees WHERE id = ?');
        expect($args)->toBe([42]);
    });

    // Positional '?' placeholders are not reusable, so each occurrence of a name
    // emits its own placeholder, each filled with the same bound value.
    it('emits a separate placeholder per occurrence of a re-used name', function () {
        $q = Q::select(Q::n('*'))
            ->from(Q::n('employees'))
            ->where(Q::or(
                Q::n('firstname')->like(Q::bind('search')),
                Q::n('lastname')->like(Q::bind('search')),
            ));

        [$sql, $args] = Q::build($q)->withNamedArgs(['search' => 'Jo%'])->toSql();

        expect($sql)->toBe('SELECT * FROM employees WHERE firstname LIKE ? OR lastname LIKE ?');
        expect($args)->toBe(['Jo%', 'Jo%']);
    });

    it('binds multiple named args', function () {
        $q = Q::select(Q::n('*'))
            ->from(Q::n('employees'))
            ->where(Q::and(
                Q::or(
                    Q::n('firstname')->like(Q::bind('search')),
                    Q::n('lastname')->like(Q::bind('search')),
                ),
                Q::n('active')->eq(Q::bind('active')),
            ));

        [$sql, $args] = Q::build($q)->withNamedArgs(['search' => 'Jo%', 'active' => true])->toSql();

        expect($sql)->toBe('SELECT * FROM employees WHERE (firstname LIKE ? OR lastname LIKE ?) AND active = ?');
        expect($args)->toBe(['Jo%', 'Jo%', true]);
    });

    it('errors on a missing named arg', function () {
        $q = Q::select(Q::n('*'))->from(Q::n('employees'))->where(Q::n('id')->eq(Q::bind('id')));

        expect(static fn () => Q::build($q)->toSql())->toThrow(QueryBuilderException::class, 'missing named argument "id"');
    });

    it('mixes arg and bind', function () {
        $q = Q::select(Q::n('*'))
            ->from(Q::n('employees'))
            ->where(Q::and(
                Q::or(
                    Q::n('firstname')->like(Q::bind('search')),
                    Q::n('lastname')->like(Q::bind('search')),
                ),
                Q::n('active')->eq(Q::arg(true)),
            ));

        [$sql, $args] = Q::build($q)->withNamedArgs(['search' => 'Jo%'])->toSql();

        expect($sql)->toBe('SELECT * FROM employees WHERE (firstname LIKE ? OR lastname LIKE ?) AND active = ?');
        expect($args)->toBe(['Jo%', 'Jo%', true]);
    });
});
