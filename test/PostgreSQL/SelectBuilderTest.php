<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\Test\PostgreSQL;

use Flowpack\QueryObjectBuilder\PostgreSQL\Q;
use Flowpack\QueryObjectBuilder\Test\AssertSql;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SelectBuilderTest extends TestCase
{
    use AssertSql;

    #[Test]
    public function withAndJson(): void
    {
        $myCategory = 'SQL Hacks';

        $q = Q::with('author_json')->as(
            Q::select(
                Q::n('authors.author_id'),
            )
                ->select(
                    Q\Func::jsonBuildObject()
                        ->prop('id', Q::n('authors.author_id'))
                        ->prop('name', Q::n('authors.name')),
                )->as('json')
                ->from(Q::n('authors')),
        )
            ->select(
                Q::n('posts.post_id'),
                Q\Func::jsonBuildObject()
                    ->prop('title', Q::n('posts.title'))
                    ->prop('author', Q::n('author_json.json')),
            )
            ->from(Q::n('posts'))
            ->leftJoin(Q::n('author_json'))->on(Q::n('posts.author_id')->eq(Q::n('author_json.author_id')))
            ->where(Q::n('posts.category')->eq(Q::arg($myCategory)))
            ->orderBy(Q::n('posts.created_at'))->desc()->nullsLast();

        $this->assertSqlWriterEquals(
        // language=PostgreSQL
            <<<'SQL'
            WITH author_json AS (
                SELECT
                    authors.author_id,
                    json_build_object('id', authors.author_id, 'name', authors.name) AS json
                FROM
                    authors
            )
            SELECT
                posts.post_id,
                json_build_object('title', posts.title, 'author', author_json.json)
            FROM
                posts
                LEFT JOIN author_json ON posts.author_id = author_json.author_id
            WHERE
                posts.category = $1
            ORDER BY
                posts.created_at DESC NULLS LAST
            SQL,
            [$myCategory],
            $q,
        );
    }

    #[Test]
    public function example1(): void
    {
        $q = Q::select(Q::n('f.title'), Q::n('f.did'), Q::n('d.name'), Q::n('f.date_prod'), Q::n('f.kind'))
            ->from(Q::n('distributors'))->as('d')->join(Q::n('films'))->as('f')->using('did');

        $this->assertSqlWriterEquals(
        // language=PostgreSQL
            <<<'SQL'
            SELECT f.title, f.did, d.name, f.date_prod, f.kind
                FROM distributors AS d JOIN films AS f USING (did)
            SQL,
            null,
            $q,
        );
    }
}
