<?php

declare(strict_types=1);

use Flowpack\QueryObjectBuilder\PostgreSQL\Q;

describe('JsonBuildObject', function () {
    it('nests objects', function () {
        $b = Q\Func::jsonBuildObject()
            ->prop('name', Q::string('Henry'))
            ->prop('address', Q\Func::jsonBuildObject()
                ->prop('street', Q::string('Main Street')));

        expect($b)->toRenderSql(
            "json_build_object('name','Henry','address',json_build_object('street','Main Street'))",
            null,
        );
    });

    it('is immutable across prop and unset', function () {
        $b1 = Q\Func::jsonBuildObject()->prop('name', Q::string('Henry'));
        $b2 = $b1->prop('age', Q::int(42));
        // Setting an existing key replaces its value while keeping its position.
        $b3 = $b2->prop('name', Q::string('John J.'));
        $b4 = $b3->unset('age');

        expect($b1)->toRenderSql("json_build_object('name','Henry')", null);
        expect($b2)->toRenderSql("json_build_object('name','Henry','age',42)", null);
        expect($b3)->toRenderSql("json_build_object('name','John J.','age',42)", null);
        expect($b4)->toRenderSql("json_build_object('name','John J.')", null);
    });
});
