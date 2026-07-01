<?php

declare(strict_types=1);

use Flowpack\QueryObjectBuilder\MySQL\Builder\JsonObjectBuilder;
use Flowpack\QueryObjectBuilder\MySQL\Q;

// JSON shapes are assembled from the constructor and aggregate functions documented at
// https://dev.mysql.com/doc/refman/8.4/en/json-creation-functions.html (JSON_OBJECT,
// JSON_ARRAY) and
// https://dev.mysql.com/doc/refman/8.4/en/aggregate-functions.html#function_json-arrayagg
// (JSON_ARRAYAGG, JSON_OBJECTAGG). Per those docs JSON_OBJECT takes an alternating
// key/value list, JSON_ARRAY() is the empty array, and both aggregates return NULL for
// an empty result set — hence the COALESCE(..., JSON_ARRAY()) idiom below.
describe('MySQL JSON shape selection', function () {
    describe('JSON_OBJECT per row', function () {
        it('builds a JSON object per row, nesting an object from a CTE', function () {
            $category = 'SQL Hacks';

            $q = Q::with('author_json')->as(
                Q::select(Q::n('authors.author_id'))
                    ->select(
                        Q\Func::jsonObject()
                            ->prop('id', Q::n('authors.author_id'))
                            ->prop('name', Q::n('authors.name')),
                    )->as('json')
                    ->from(Q::n('authors')),
            )
                ->select(
                    Q::n('posts.post_id'),
                    Q\Func::jsonObject()
                        ->prop('title', Q::n('posts.title'))
                        ->prop('author', Q::n('author_json.json')),
                )
                ->from(Q::n('posts'))
                ->leftJoin(Q::n('author_json'))->on(Q::n('posts.author_id')->eq(Q::n('author_json.author_id')))
                ->where(Q::n('posts.category')->eq(Q::arg($category)))
                ->orderBy(Q::n('posts.created_at'))->desc();

            // language=MySQL
            expect($q)->toRenderSql(<<<'SQL'
                WITH
                  author_json AS (
                    SELECT
                      authors.author_id,
                      JSON_OBJECT('id', authors.author_id, 'name', authors.name) AS json
                    FROM
                      authors
                  )
                SELECT
                  posts.post_id,
                  JSON_OBJECT('title', posts.title, 'author', author_json.json)
                FROM
                  posts
                  LEFT JOIN author_json ON posts.author_id = author_json.author_id
                WHERE
                  posts.category = ?
                ORDER BY
                  posts.created_at DESC
                SQL, [$category]);
        });
    });

    describe('JSON_ARRAYAGG of child objects', function () {
        it('rolls up child rows into a JSON array with a LEFT JOIN and GROUP BY', function () {
            $q = Q::select(
                Q::n('u.id'),
                Q::n('u.name'),
                Q\Func::jsonArrayAgg(
                    Q\Func::jsonObject()
                        ->prop('id', Q::n('o.id'))
                        ->prop('total', Q::n('o.total')),
                ),
            )->as('orders')
                ->from(Q::n('users'))->as('u')
                ->leftJoin(Q::n('orders'))->as('o')->on(Q::n('o.user_id')->eq(Q::n('u.id')))
                ->groupBy(Q::n('u.id'), Q::n('u.name'));

            // language=MySQL
            expect($q)->toRenderSql(<<<'SQL'
                SELECT
                  u.id,
                  u.name,
                  JSON_ARRAYAGG(JSON_OBJECT('id', o.id, 'total', o.total)) AS orders
                FROM
                  users AS u
                  LEFT JOIN orders AS o ON o.user_id = u.id
                GROUP BY
                  u.id, u.name
                SQL, null);
        });

        it('collects child rows via a correlated subquery', function () {
            $q = Q::select(
                Q::n('p.id'),
                Q::n('p.name'),
                Q::select(
                    Q::coalesce(
                        Q\Func::jsonArrayAgg(
                            Q\Func::jsonObject()
                                ->prop('id', Q::n('c.id'))
                                ->prop('name', Q::n('c.name')),
                        ),
                        Q\Func::jsonArray(),
                    ),
                )
                    ->from(Q::n('child'))->as('c')
                    ->where(Q::n('c.parent_id')->eq(Q::n('p.id'))),
            )->as('children')
                ->from(Q::n('parent'))->as('p');

            // language=MySQL
            expect($q)->toRenderSql(<<<'SQL'
                SELECT
                  p.id,
                  p.name,
                  (
                    SELECT
                      COALESCE(JSON_ARRAYAGG(JSON_OBJECT('id', c.id, 'name', c.name)), JSON_ARRAY())
                    FROM
                      child AS c
                    WHERE
                      c.parent_id = p.id
                  ) AS children
                FROM
                  parent AS p
                SQL, null);
        });

        // JSON_ARRAYAGG returns NULL (not []) when a LEFT JOIN produces no child rows,
        // so wrap it in COALESCE(..., JSON_ARRAY()) to normalise the empty case.
        it('forces an empty JSON array instead of NULL with COALESCE', function () {
            $q = Q::select(
                Q::n('a.author_id'),
                Q::coalesce(
                    Q\Func::jsonArrayAgg(
                        Q\Func::jsonObject()
                            ->prop('ID', Q::n('b.book_id'))
                            ->prop('Title', Q::n('b.title'))
                            ->prop('PublicationYear', Q::n('b.publication_year')),
                    ),
                    Q\Func::jsonArray(),
                ),
            )->as('books')
                ->from(Q::n('authors'))->as('a')
                ->leftJoin(Q::n('books'))->as('b')->on(Q::n('b.author_id')->eq(Q::n('a.author_id')))
                ->groupBy(Q::n('a.author_id'));

            // language=MySQL
            expect($q)->toRenderSql(<<<'SQL'
                SELECT
                  a.author_id,
                  COALESCE(
                    JSON_ARRAYAGG(
                      JSON_OBJECT('ID', b.book_id, 'Title', b.title, 'PublicationYear', b.publication_year)
                    ),
                    JSON_ARRAY()
                  ) AS books
                FROM
                  authors AS a
                  LEFT JOIN books AS b ON b.author_id = a.author_id
                GROUP BY
                  a.author_id
                SQL, null);
        });
    });

    describe('JSON_OBJECTAGG keyed by a column', function () {
        it('builds an object keyed by child id', function () {
            $q = Q::select(
                Q::n('p.id'),
                Q::n('p.name'),
                Q\Func::jsonObjectAgg(
                    Q::n('c.id'),
                    Q\Func::jsonObject()
                        ->prop('id', Q::n('c.id'))
                        ->prop('name', Q::n('c.name')),
                ),
            )->as('children_by_id')
                ->from(Q::n('parent'))->as('p')
                ->leftJoin(Q::n('child'))->as('c')->on(Q::n('c.parent_id')->eq(Q::n('p.id')))
                ->groupBy(Q::n('p.id'), Q::n('p.name'));

            // language=MySQL
            expect($q)->toRenderSql(<<<'SQL'
                SELECT
                  p.id,
                  p.name,
                  JSON_OBJECTAGG(c.id, JSON_OBJECT('id', c.id, 'name', c.name)) AS children_by_id
                FROM
                  parent AS p
                  LEFT JOIN child AS c ON c.parent_id = p.id
                GROUP BY
                  p.id, p.name
                SQL, null);
        });
    });

    describe('complex nested JSON', function () {
        it('with CTEs', function () {
            $q = Q::with('author_books')->as(
                Q::select(Q::n('author_id'))
                    ->select(
                        Q::coalesce(
                            Q\Func::jsonArrayAgg(Q\Func::jsonObject()
                                ->prop('Title', Q::n('books.title'))
                                ->prop('AuthorID', Q::n('books.author_id'))
                                ->prop('PublicationYear', Q::n('books.publication_year'))
                                ->prop('ID', Q::n('books.book_id'))),
                            Q\Func::jsonArray(),
                        ),
                    )->as('books')
                    ->from(Q::n('books'))
                    ->groupBy(Q::n('author_id')),
            )
                ->with('book_genres')->as(
                    Q::select(Q::n('book_id'))
                        ->select(
                            Q::coalesce(
                                Q\Func::jsonArrayAgg(Q\Func::jsonObject()
                                    ->prop('GenreID', Q::n('genres.genre_id'))
                                    ->prop('Name', Q::n('genres.name'))),
                                Q\Func::jsonArray(),
                            ),
                        )->as('genres')
                        ->from(Q::n('book_genre'))
                        ->join(Q::n('genres'))->using('genre_id')
                        ->groupBy(Q::n('book_id')),
                )
                ->select(
                    Q\Func::jsonObject()
                        ->prop('Title', Q::n('books.title'))
                        ->prop('AuthorID', Q::n('books.author_id'))
                        ->prop('PublicationYear', Q::n('books.publication_year'))
                        ->prop('ID', Q::n('books.book_id'))
                        ->prop('Author', Q\Func::jsonObject()
                            ->prop('AuthorID', Q::n('authors.author_id'))
                            ->prop('Name', Q::n('authors.name'))
                            ->prop('Books', Q::n('author_books.books')))
                        ->prop('Genres', Q::n('book_genres.genres')),
                )
                ->from(Q::n('books'))
                ->leftJoin(Q::n('authors'))->using('author_id')
                ->leftJoin(Q::n('author_books'))->using('author_id')
                ->leftJoin(Q::n('book_genres'))->using('book_id')
                ->where(Q::n('books.book_id')->eq(Q::arg(2)));

            // language=MySQL
            expect($q)->toRenderSql(<<<'SQL'
                WITH
                  author_books AS (
                    SELECT
                      author_id,
                      COALESCE(
                        JSON_ARRAYAGG(
                          JSON_OBJECT(
                            'Title', books.title,
                            'AuthorID', books.author_id,
                            'PublicationYear', books.publication_year,
                            'ID', books.book_id
                          )
                        ),
                        JSON_ARRAY()
                      ) AS books
                    FROM
                      books
                    GROUP BY
                      author_id
                  ),
                  book_genres AS (
                    SELECT
                      book_id,
                      COALESCE(
                        JSON_ARRAYAGG(JSON_OBJECT('GenreID', genres.genre_id, 'Name', genres.name)),
                        JSON_ARRAY()
                      ) AS genres
                    FROM
                      book_genre
                      JOIN genres USING (genre_id)
                    GROUP BY
                      book_id
                  )
                SELECT
                  JSON_OBJECT(
                    'Title', books.title,
                    'AuthorID', books.author_id,
                    'PublicationYear', books.publication_year,
                    'ID', books.book_id,
                    'Author', JSON_OBJECT(
                      'AuthorID', authors.author_id,
                      'Name', authors.name,
                      'Books', author_books.books
                    ),
                    'Genres', book_genres.genres
                  )
                FROM
                  books
                  LEFT JOIN authors USING (author_id)
                  LEFT JOIN author_books USING (author_id)
                  LEFT JOIN book_genres USING (book_id)
                WHERE
                  books.book_id = ?
                SQL, [2]);
        });

        it('with correlated subselects', function () {
            $q = Q::select(
                Q\Func::jsonObject()
                    ->prop('Title', Q::n('books.title'))
                    ->prop('AuthorID', Q::n('books.author_id'))
                    ->prop('PublicationYear', Q::n('books.publication_year'))
                    ->prop('ID', Q::n('books.book_id'))
                    ->prop('Author', Q::select(
                        Q\Func::jsonObject()
                            ->prop('AuthorID', Q::n('authors.author_id'))
                            ->prop('Name', Q::n('authors.name'))
                            ->prop('Books', Q::select(
                                Q::coalesce(
                                    Q\Func::jsonArrayAgg(Q\Func::jsonObject()
                                        ->prop('Title', Q::n('books.title'))
                                        ->prop('ID', Q::n('books.book_id'))),
                                    Q\Func::jsonArray(),
                                ),
                            )
                                ->from(Q::n('books'))
                                ->where(Q::n('books.author_id')->eq(Q::n('authors.author_id')))),
                    )
                        ->from(Q::n('authors'))
                        ->where(Q::n('authors.author_id')->eq(Q::n('books.author_id'))))
                    ->prop('Genres', Q::select(
                        Q::coalesce(
                            Q\Func::jsonArrayAgg(Q\Func::jsonObject()
                                ->prop('GenreID', Q::n('genres.genre_id'))
                                ->prop('Name', Q::n('genres.name'))),
                            Q\Func::jsonArray(),
                        ),
                    )
                        ->from(Q::n('book_genre'))
                        ->leftJoin(Q::n('genres'))->using('genre_id')
                        ->where(Q::n('book_genre.book_id')->eq(Q::n('books.book_id')))),
            )
                ->from(Q::n('books'))
                ->where(Q::n('books.book_id')->eq(Q::arg(2)));

            // language=MySQL
            expect($q)->toRenderSql(<<<'SQL'
                SELECT
                  JSON_OBJECT(
                    'Title', books.title,
                    'AuthorID', books.author_id,
                    'PublicationYear', books.publication_year,
                    'ID', books.book_id,
                    'Author', (
                      SELECT
                        JSON_OBJECT(
                          'AuthorID', authors.author_id,
                          'Name', authors.name,
                          'Books', (
                            SELECT
                              COALESCE(
                                JSON_ARRAYAGG(JSON_OBJECT('Title', books.title, 'ID', books.book_id)),
                                JSON_ARRAY()
                              )
                            FROM
                              books
                            WHERE
                              books.author_id = authors.author_id
                          )
                        )
                      FROM
                        authors
                      WHERE
                        authors.author_id = books.author_id
                    ),
                    'Genres', (
                      SELECT
                        COALESCE(
                          JSON_ARRAYAGG(JSON_OBJECT('GenreID', genres.genre_id, 'Name', genres.name)),
                          JSON_ARRAY()
                        )
                      FROM
                        book_genre
                        LEFT JOIN genres USING (genre_id)
                      WHERE
                        book_genre.book_id = books.book_id
                    )
                  )
                FROM
                  books
                WHERE
                  books.book_id = ?
                SQL, [2]);
        });

        it('without nested relations', function () {
            $q = Q::select(
                Q\Func::jsonObject()
                    ->prop('Title', Q::n('books.title'))
                    ->prop('AuthorID', Q::n('books.author_id'))
                    ->prop('PublicationYear', Q::n('books.publication_year'))
                    ->prop('ID', Q::n('books.book_id')),
            )
                ->from(Q::n('books'))
                ->where(Q::n('books.book_id')->eq(Q::arg(2)));

            // language=MySQL
            expect($q)->toRenderSql(<<<'SQL'
                SELECT
                  JSON_OBJECT(
                    'Title', books.title,
                    'AuthorID', books.author_id,
                    'PublicationYear', books.publication_year,
                    'ID', books.book_id
                  )
                FROM
                  books
                WHERE
                  books.book_id = ?
                SQL, [2]);
        });

        it('builds the JSON object conditionally with propIf', function () {
            // The builder assembles a shape incrementally, so an optional property
            // drops out with propIf() — no hand-composed argument list needed.
            $buildBookJson = static fn (bool $includeAuthor): JsonObjectBuilder => Q\Func::jsonObject()
                ->prop('Title', Q::n('books.title'))
                ->prop('ID', Q::n('books.book_id'))
                ->propIf($includeAuthor, 'AuthorID', Q::n('books.author_id'));

            $with = Q::select($buildBookJson(true))->from(Q::n('books'));
            $without = Q::select($buildBookJson(false))->from(Q::n('books'));

            // language=MySQL
            expect($with)->toRenderSql(<<<'SQL'
                SELECT
                  JSON_OBJECT('Title', books.title, 'ID', books.book_id, 'AuthorID', books.author_id)
                FROM
                  books
                SQL, null);

            // language=MySQL
            expect($without)->toRenderSql(<<<'SQL'
                SELECT
                  JSON_OBJECT('Title', books.title, 'ID', books.book_id)
                FROM
                  books
                SQL, null);
        });
    });
});
