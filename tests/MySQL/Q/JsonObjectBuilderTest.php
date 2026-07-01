<?php

declare(strict_types=1);

use Flowpack\QueryObjectBuilder\MySQL\Builder\JsonObjectBuilder;
use Flowpack\QueryObjectBuilder\MySQL\Q;

describe('JsonObjectBuilder', function () {
    it('renders an empty object', function () {
        expect(Q\Func::jsonObject())->toRenderSql('JSON_OBJECT()');
    });

    it('nests objects', function () {
        $b = Q\Func::jsonObject()
            ->prop('name', Q::string('Henry'))
            ->prop('address', Q\Func::jsonObject()
                ->prop('street', Q::string('Main Street')));

        expect($b)->toRenderSql(
            "JSON_OBJECT('name', 'Henry', 'address', JSON_OBJECT('street', 'Main Street'))",
            null,
        );
    });

    it('is immutable across prop and unset', function () {
        $b1 = Q\Func::jsonObject()->prop('name', Q::string('Henry'));
        $b2 = $b1->prop('age', Q::int(42));
        // Setting an existing key replaces its value while keeping its position.
        $b3 = $b2->prop('name', Q::string('John J.'));
        $b4 = $b3->unset('age');

        expect($b1)->toRenderSql("JSON_OBJECT('name', 'Henry')", null);
        expect($b2)->toRenderSql("JSON_OBJECT('name', 'Henry', 'age', 42)", null);
        expect($b3)->toRenderSql("JSON_OBJECT('name', 'John J.', 'age', 42)", null);
        expect($b4)->toRenderSql("JSON_OBJECT('name', 'John J.')", null);
    });

    it('adds a property only when the condition holds with propIf', function () {
        $build = static fn (bool $withAge): JsonObjectBuilder => Q\Func::jsonObject()
            ->prop('name', Q::string('Henry'))
            ->propIf($withAge, 'age', Q::int(42));

        expect($build(true))->toRenderSql("JSON_OBJECT('name', 'Henry', 'age', 42)");
        expect($build(false))->toRenderSql("JSON_OBJECT('name', 'Henry')");
    });

    it('applies a block only when the condition holds with applyIf', function () {
        $build = static fn (bool $withAddress): JsonObjectBuilder => Q\Func::jsonObject()
            ->prop('name', Q::string('Henry'))
            ->applyIf(
                $withAddress,
                static fn (JsonObjectBuilder $b): JsonObjectBuilder => $b->prop('city', Q::string('Berlin')),
            );

        expect($build(true))->toRenderSql("JSON_OBJECT('name', 'Henry', 'city', 'Berlin')");
        expect($build(false))->toRenderSql("JSON_OBJECT('name', 'Henry')");
    });
});
