<?php

declare(strict_types=1);

use Flowpack\QueryObjectBuilder\PostgreSQL\Builder\JsonBuildObjectBuilder;
use Flowpack\QueryObjectBuilder\PostgreSQL\Q;

describe('jsonb build object query', function () {
    it('selects a jsonb object and can be modified afterwards', function () {
        $b = Q::selectJson(
            Q\Func::jsonbBuildObject()
                ->prop('id', Q::n('authors.author_id'))
                ->prop('name', Q::n('authors.name')),
        )
            ->from(Q::n('authors'))
            ->where(Q::n('authors.author_id')->eq(Q::arg(123)));

        expect($b)->toRenderSql(
            "SELECT jsonb_build_object('id',authors.author_id,'name',authors.name) FROM authors WHERE authors.author_id = $1",
            [123],
        );

        $withPostCount = $b->applySelectJson(
            static fn (JsonBuildObjectBuilder $obj): JsonBuildObjectBuilder => $obj->prop('postCount', Q\Func::count(Q::n('posts'))),
        );

        expect($withPostCount)->toRenderSql(
            "SELECT jsonb_build_object('id',authors.author_id,'name',authors.name,'postCount',count(posts)) FROM authors WHERE authors.author_id = $1",
            [123],
        );
    });
});
