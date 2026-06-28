<?php

declare(strict_types=1);

use Flowpack\QueryObjectBuilder\PostgreSQL\Q;

describe('ArrayFunctions', function () {
    it('renders unnest', function () {
        $q = Q::select(Q::n('*'))
            ->from(
                Q\Func::unnest(
                    Q::array(Q::int(1), Q::int(2)),
                    Q::array(Q::string('foo'), Q::string('bar'), Q::string('baz')),
                ),
            )->as('x')->columnAliases('a', 'b');

        expect($q)->toRenderSql("SELECT * FROM unnest(ARRAY[1,2], ARRAY['foo','bar','baz']) AS x (a,b)", null);
    });

    it('renders array_append', function () {
        $q = Q::select(Q\Func::arrayAppend(Q::array(Q::int(1), Q::int(2)), Q::int(3)));

        expect($q)->toRenderSql('SELECT array_append(ARRAY[1,2], 3)', null);
    });

    it('renders array_prepend', function () {
        $q = Q::select(Q\Func::arrayPrepend(Q::int(1), Q::array(Q::int(2), Q::int(3))));

        expect($q)->toRenderSql('SELECT array_prepend(1, ARRAY[2,3])', null);
    });

    it('renders array_cat', function () {
        $q = Q::select(Q\Func::arrayCat(Q::array(Q::int(1), Q::int(2)), Q::array(Q::int(3), Q::int(4))));

        expect($q)->toRenderSql('SELECT array_cat(ARRAY[1,2], ARRAY[3,4])', null);
    });

    it('renders array_dims', function () {
        $q = Q::select(Q\Func::arrayDims(Q::array(Q::int(1), Q::int(2), Q::int(3))));

        expect($q)->toRenderSql('SELECT array_dims(ARRAY[1,2,3])', null);
    });

    it('renders array_ndims', function () {
        $q = Q::select(Q\Func::arrayNdims(Q::array(Q::int(1), Q::int(2), Q::int(3))));

        expect($q)->toRenderSql('SELECT array_ndims(ARRAY[1,2,3])', null);
    });

    it('renders array_length', function () {
        $q = Q::select(Q\Func::arrayLength(Q::array(Q::int(1), Q::int(2), Q::int(3)), Q::int(1)));

        expect($q)->toRenderSql('SELECT array_length(ARRAY[1,2,3], 1)', null);
    });

    it('renders array_lower', function () {
        $q = Q::select(Q\Func::arrayLower(Q::array(Q::int(1), Q::int(2), Q::int(3)), Q::int(1)));

        expect($q)->toRenderSql('SELECT array_lower(ARRAY[1,2,3], 1)', null);
    });

    it('renders array_upper', function () {
        $q = Q::select(Q\Func::arrayUpper(Q::array(Q::int(1), Q::int(2), Q::int(3)), Q::int(1)));

        expect($q)->toRenderSql('SELECT array_upper(ARRAY[1,2,3], 1)', null);
    });

    it('renders array_remove', function () {
        $q = Q::select(Q\Func::arrayRemove(Q::array(Q::int(1), Q::int(2), Q::int(3), Q::int(2)), Q::int(2)));

        expect($q)->toRenderSql('SELECT array_remove(ARRAY[1,2,3,2], 2)', null);
    });

    it('renders array_replace', function () {
        $q = Q::select(Q\Func::arrayReplace(Q::array(Q::int(1), Q::int(2), Q::int(3)), Q::int(2), Q::int(99)));

        expect($q)->toRenderSql('SELECT array_replace(ARRAY[1,2,3], 2, 99)', null);
    });

    describe('array_position', function () {
        it('renders without start', function () {
            $q = Q::select(Q\Func::arrayPosition(Q::array(Q::string('a'), Q::string('b'), Q::string('c')), Q::string('b')));

            expect($q)->toRenderSql("SELECT array_position(ARRAY['a','b','c'], 'b')", null);
        });

        it('renders with start', function () {
            $q = Q::select(Q\Func::arrayPosition(Q::array(Q::string('a'), Q::string('b'), Q::string('c'), Q::string('b')), Q::string('b'), Q::int(3)));

            expect($q)->toRenderSql("SELECT array_position(ARRAY['a','b','c','b'], 'b', 3)", null);
        });
    });

    it('renders array_positions', function () {
        $q = Q::select(Q\Func::arrayPositions(Q::array(Q::string('a'), Q::string('b'), Q::string('c'), Q::string('b')), Q::string('b')));

        expect($q)->toRenderSql("SELECT array_positions(ARRAY['a','b','c','b'], 'b')", null);
    });

    describe('array_to_string', function () {
        it('renders without null string', function () {
            $q = Q::select(Q\Func::arrayToString(Q::array(Q::string('a'), Q::string('b'), Q::string('c')), Q::string(',')));

            expect($q)->toRenderSql("SELECT array_to_string(ARRAY['a','b','c'], ',')", null);
        });

        it('renders with null string', function () {
            $q = Q::select(Q\Func::arrayToString(Q::array(Q::string('a'), Q::string('b'), Q::null()), Q::string(','), Q::string('*')));

            expect($q)->toRenderSql("SELECT array_to_string(ARRAY['a','b',NULL], ',', '*')", null);
        });
    });

    describe('string_to_array', function () {
        it('renders without null string', function () {
            $q = Q::select(Q\Func::stringToArray(Q::string('a,b,c'), Q::string(',')));

            expect($q)->toRenderSql("SELECT string_to_array('a,b,c', ',')", null);
        });

        it('renders with null string', function () {
            $q = Q::select(Q\Func::stringToArray(Q::string('a,b,*'), Q::string(','), Q::string('*')));

            expect($q)->toRenderSql("SELECT string_to_array('a,b,*', ',', '*')", null);
        });
    });

    describe('array_fill', function () {
        it('renders without lower bounds', function () {
            $q = Q::select(Q\Func::arrayFill(Q::string('x'), Q::array(Q::int(3))));

            expect($q)->toRenderSql("SELECT array_fill('x', ARRAY[3])", null);
        });

        it('renders with lower bounds', function () {
            $q = Q::select(Q\Func::arrayFill(Q::string('x'), Q::array(Q::int(3), Q::int(2)), Q::array(Q::int(2), Q::int(5))));

            expect($q)->toRenderSql("SELECT array_fill('x', ARRAY[3,2], ARRAY[2,5])", null);
        });
    });
});
