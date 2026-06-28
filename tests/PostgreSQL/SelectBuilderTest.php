<?php

declare(strict_types=1);

use Flowpack\QueryObjectBuilder\PostgreSQL\Builder\JsonBuildObjectBuilder;
use Flowpack\QueryObjectBuilder\PostgreSQL\Builder\SelectBuilder;
use Flowpack\QueryObjectBuilder\PostgreSQL\Q;
use Flowpack\QueryObjectBuilder\Test\PostgreSQL\AuthorQueryOpts;
use Flowpack\QueryObjectBuilder\Test\PostgreSQL\BookQueryOpts;

describe('SelectBuilder', function () {
    it('builds a WITH query selecting JSON', function () {
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

        // language=PostgreSQL
        expect($q)->toRenderSql(<<<'SQL'
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
            SQL, [$myCategory]);
    });

    describe('complex nested JSON', function () {
        it('with CTEs', function () {
            $bookJSON = Q\Func::jsonBuildObject()
                ->prop('Title', Q::n('books.title'))
                ->prop('AuthorID', Q::n('books.author_id'))
                ->prop('PublicationYear', Q::n('books.publication_year'))
                ->prop('CreatedAt', Q::n('books.created_at'))
                ->prop('UpdatedAt', Q::n('books.updated_at'))
                ->prop('ID', Q::n('books.book_id'));

            $authorJSON = Q\Func::jsonBuildObject()
                ->prop('AuthorID', Q::n('authors.author_id'))
                ->prop('Name', Q::n('authors.name'));

            $genreJSON = Q\Func::jsonBuildObject()
                ->prop('GenreID', Q::n('genres.genre_id'))
                ->prop('Name', Q::n('genres.name'));

            $opts = new BookQueryOpts(
                includeGenres: true,
                includeAuthor: true,
                authorOpts: new AuthorQueryOpts(includeBooks: true),
            );

            $q = Q::selectJson($bookJSON)
                ->from(Q::n('books'))
                ->leftJoin(Q::n('authors'))->using('author_id')
                ->where(Q::n('books.book_id')->eq(Q::arg(2)));

            if ($opts->includeAuthor) {
                $q = $q->applySelectJson(static fn (JsonBuildObjectBuilder $obj): JsonBuildObjectBuilder => $obj->prop(
                    'Author',
                    $authorJSON->propIf($opts->authorOpts->includeBooks, 'Books', Q::n('author_books.books')),
                ));

                if ($opts->authorOpts->includeBooks) {
                    $q = $q->appendWith(
                        Q::with('author_books')->as(
                            Q::select(Q::n('author_id'))
                                ->select(
                                    Q::coalesce(
                                        Q\Func::jsonAgg($bookJSON)->orderBy(Q::n('publication_year')),
                                        Q::string('[]'),
                                    ),
                                )->as('books')
                                ->from(Q::n('books'))
                                ->groupBy(Q::n('author_id')),
                        ),
                    )->leftJoin(Q::n('author_books'))->using('author_id');
                }
            }

            if ($opts->includeGenres) {
                $q = $q->appendWith(
                    Q::with('book_genres')->as(
                        Q::select(Q::n('book_id'))
                            ->select(
                                Q::coalesce(
                                    Q\Func::jsonAgg($genreJSON)->orderBy(Q::n('name')),
                                    Q::string('[]'),
                                ),
                            )->as('genres')
                            ->from(Q::n('book_genre'))
                            ->join(Q::n('genres'))->using('genre_id')
                            ->groupBy(Q::n('book_id')),
                    ),
                )
                    ->applySelectJson(static fn (JsonBuildObjectBuilder $obj): JsonBuildObjectBuilder => $obj->prop('Genres', Q::n('book_genres.genres')))
                    ->leftJoin(Q::n('book_genres'))->using('book_id');
            }

            // language=PostgreSQL
            expect($q)->toRenderSql(<<<'SQL'
                WITH author_books AS (SELECT author_id,
                                             COALESCE(json_agg(json_build_object('Title', books.title, 'AuthorID', books.author_id,
                                                                                 'PublicationYear', books.publication_year, 'CreatedAt',
                                                                                 books.created_at, 'UpdatedAt', books.updated_at, 'ID',
                                                                                 books.book_id) ORDER BY publication_year),
                                                      '[]') AS books
                                      FROM books
                                      GROUP BY author_id),
                     book_genres AS (SELECT book_id,
                                            COALESCE(json_agg(json_build_object('GenreID', genres.genre_id, 'Name', genres.name)
                                                              ORDER BY name), '[]') AS genres
                                     FROM book_genre
                                              JOIN genres USING (genre_id)
                                     GROUP BY book_id)
                SELECT json_build_object('Title', books.title, 'AuthorID', books.author_id, 'PublicationYear', books.publication_year,
                                         'CreatedAt', books.created_at, 'UpdatedAt', books.updated_at, 'ID', books.book_id, 'Author',
                                         json_build_object('AuthorID', authors.author_id, 'Name', authors.name, 'Books',
                                                           author_books.books), 'Genres', book_genres.genres)
                FROM books
                         LEFT JOIN authors USING (author_id)
                         LEFT JOIN author_books USING (author_id)
                         LEFT JOIN book_genres USING (book_id)
                WHERE books.book_id = $1
                SQL, [2]);
        });

        describe('with subselects', function () {
            $genreJSON = Q\Func::jsonBuildObject()
                ->prop('GenreID', Q::n('genres.genre_id'))
                ->prop('Name', Q::n('genres.name'));

            $baseBookJSON = Q\Func::jsonBuildObject()
                ->prop('Title', Q::n('books.title'))
                ->prop('AuthorID', Q::n('books.author_id'))
                ->prop('PublicationYear', Q::n('books.publication_year'))
                ->prop('CreatedAt', Q::n('books.created_at'))
                ->prop('UpdatedAt', Q::n('books.updated_at'))
                ->prop('ID', Q::n('books.book_id'));

            $selectAuthorBooks = Q::select(
                Q::coalesce(Q\Func::jsonAgg($baseBookJSON)->orderBy(Q::n('books.publication_year')), Q::string('[]')),
            )
                ->from(Q::n('books'))
                ->where(Q::n('books.author_id')->eq(Q::n('authors.author_id')));

            $buildAuthorJSON = static fn (AuthorQueryOpts $opts): JsonBuildObjectBuilder => Q\Func::jsonBuildObject()
                ->prop('AuthorID', Q::n('authors.author_id'))
                ->prop('Name', Q::n('authors.name'))
                ->propIf($opts->includeBooks, 'Books', $selectAuthorBooks);

            $selectAuthors = static fn (AuthorQueryOpts $opts): SelectBuilder => Q::selectJson($buildAuthorJSON($opts))
                ->from(Q::n('authors'));

            $selectBookAuthor = static fn (BookQueryOpts $opts): SelectBuilder => $selectAuthors($opts->authorOpts)
                ->where(Q::n('authors.author_id')->eq(Q::n('books.author_id')));

            $buildBookJSON = static fn (BookQueryOpts $opts): JsonBuildObjectBuilder => $baseBookJSON
                ->propIf($opts->includeAuthor, 'Author', $selectBookAuthor($opts))
                ->applyIf($opts->includeGenres, static fn (JsonBuildObjectBuilder $b): JsonBuildObjectBuilder => $b->prop(
                    'Genres',
                    Q::select(Q::coalesce(Q\Func::jsonAgg($genreJSON)->orderBy(Q::n('genres.name')), Q::string('[]')))
                        ->from(Q::n('book_genre'))
                        ->leftJoin(Q::n('genres'))->using('genre_id')
                        ->where(Q::n('book_genre.book_id')->eq(Q::n('books.book_id'))),
                ));

            $selectBook = static fn (BookQueryOpts $opts): SelectBuilder => Q::selectJson($buildBookJSON($opts))
                ->from(Q::n('books'));

            it('with all options', function () use ($selectBook) {
                $opts = new BookQueryOpts(
                    includeGenres: true,
                    includeAuthor: true,
                    authorOpts: new AuthorQueryOpts(includeBooks: true),
                );
                $q = $selectBook($opts)->where(Q::n('books.book_id')->eq(Q::arg(2)));

                // language=PostgreSQL
                expect($q)->toRenderSql(<<<'SQL'
                    SELECT json_build_object(
                                   'Title', books.title,
                                   'AuthorID', books.author_id,
                                   'PublicationYear', books.publication_year,
                                   'CreatedAt', books.created_at,
                                   'UpdatedAt', books.updated_at,
                                   'ID', books.book_id,
                                   'Author', (SELECT json_build_object(
                                                             'AuthorID', authors.author_id,
                                                             'Name', authors.name,
                                                             'Books', (SELECT COALESCE(
                                                                                      json_agg(
                                                                                              json_build_object(
                                                                                                      'Title', books.title,
                                                                                                      'AuthorID', books.author_id,
                                                                                                      'PublicationYear',
                                                                                                      books.publication_year,
                                                                                                      'CreatedAt', books.created_at,
                                                                                                      'UpdatedAt', books.updated_at,
                                                                                                      'ID', books.book_id
                                                                                                  )
                                                                                              ORDER BY books.publication_year),
                                                                                      '[]'
                                                                                  )
                                                                       FROM books
                                                                       WHERE books.author_id = authors.author_id)
                                                             )
                                              FROM authors
                                              WHERE authors.author_id = books.author_id),
                                   'Genres', (SELECT COALESCE(
                                                     json_agg(
                                                             json_build_object(
                                                                     'GenreID', genres.genre_id,
                                                                     'Name', genres.name
                                                                 )
                                                             ORDER BY genres.name),
                                                     '[]'
                                                 )
                                              FROM book_genre
                                                   LEFT JOIN genres USING (genre_id)
                                              WHERE book_genre.book_id = books.book_id)
                           )
                    FROM books
                    WHERE books.book_id = $1
                    SQL, [2]);
            });

            it('without options', function () use ($selectBook) {
                $q = $selectBook(new BookQueryOpts())->where(Q::n('books.book_id')->eq(Q::arg(2)));

                // language=PostgreSQL
                expect($q)->toRenderSql(<<<'SQL'
                    SELECT json_build_object('Title', books.title, 'AuthorID', books.author_id, 'PublicationYear', books.publication_year,
                                             'CreatedAt', books.created_at, 'UpdatedAt', books.updated_at, 'ID', books.book_id)
                    FROM books
                    WHERE books.book_id = $1
                    SQL, [2]);
            });

            it('with a modified JSON selection', function () use ($selectBook) {
                $q = $selectBook(new BookQueryOpts())
                    ->applySelectJson(static fn (JsonBuildObjectBuilder $obj): JsonBuildObjectBuilder => $obj
                        ->unset('CreatedAt')
                        ->unset('UpdatedAt'))
                    ->orderBy(Q::n('books.publication_year'))
                    ->limit(Q::int(10))
                    ->offset(Q::arg(5));

                // language=PostgreSQL
                expect($q)->toRenderSql(<<<'SQL'
                    SELECT json_build_object('Title',books.title,'AuthorID',books.author_id,'PublicationYear',books.publication_year,'ID',books.book_id) FROM books ORDER BY books.publication_year LIMIT 10 OFFSET $1
                    SQL, [5]);
            });
        });
    });

    describe('examples', function () {
        it('example 1', function () {
            $q = Q::select(Q::n('f.title'), Q::n('f.did'), Q::n('d.name'), Q::n('f.date_prod'), Q::n('f.kind'))
                ->from(Q::n('distributors'))->as('d')->join(Q::n('films'))->as('f')->using('did');

            // language=PostgreSQL
            expect($q)->toRenderSql(<<<'SQL'
                SELECT f.title, f.did, d.name, f.date_prod, f.kind
                    FROM distributors AS d JOIN films AS f USING (did)
                SQL, null);
        });
    });
});
