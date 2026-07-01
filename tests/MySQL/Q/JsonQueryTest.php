<?php

declare(strict_types=1);

use Flowpack\QueryObjectBuilder\MySQL\Builder\JsonObjectBuilder;
use Flowpack\QueryObjectBuilder\MySQL\Q;

describe('JSON object query', function () {
    it('selects a json object and can be modified afterwards', function () {
        $b = Q::selectJson(
            Q\Func::jsonObject()
                ->prop('id', Q::n('authors.author_id'))
                ->prop('name', Q::n('authors.name')),
        )
            ->from(Q::n('authors'))
            ->where(Q::n('authors.author_id')->eq(Q::arg(123)));

        expect($b)->toRenderSql(
            "SELECT JSON_OBJECT('id', authors.author_id, 'name', authors.name) FROM authors WHERE authors.author_id = ?",
            [123],
        );

        // The select builder acts as a blueprint: the JSON selection can be modified later.
        $withPostCount = $b->applySelectJson(
            static fn (JsonObjectBuilder $obj): JsonObjectBuilder => $obj->prop('postCount', Q\Func::count(Q::n('posts'))),
        );

        expect($withPostCount)->toRenderSql(
            "SELECT JSON_OBJECT('id', authors.author_id, 'name', authors.name, 'postCount', COUNT(posts)) FROM authors WHERE authors.author_id = ?",
            [123],
        );
    });

    it('selects a json object with an alias', function () {
        $b = Q::selectJson(
            Q\Func::jsonObject()
                ->prop('id', Q::n('authors.author_id'))
                ->prop('name', Q::n('authors.name')),
        )->as('myjson')
            ->from(Q::n('authors'))
            ->where(Q::n('authors.author_id')->eq(Q::arg(123)));

        expect($b)->toRenderSql(
            "SELECT JSON_OBJECT('id', authors.author_id, 'name', authors.name) AS myjson FROM authors WHERE authors.author_id = ?",
            [123],
        );
    });

    it('starts an empty JSON selection and builds it up with applySelectJson', function () {
        $b = Q::select()
            ->from(Q::n('authors'))
            ->applySelectJson(
                static fn (JsonObjectBuilder $obj): JsonObjectBuilder => $obj->prop('name', Q::n('authors.name')),
            );

        expect($b)->toRenderSql("SELECT JSON_OBJECT('name', authors.name) FROM authors");
    });

    it('keeps the JSON selection first, before other select elements', function () {
        $b = Q::selectJson(Q\Func::jsonObject()->prop('name', Q::n('name')))
            ->select(Q::n('id'))
            ->from(Q::n('authors'));

        expect($b)->toRenderSql("SELECT JSON_OBJECT('name', name), id FROM authors");
    });
});
