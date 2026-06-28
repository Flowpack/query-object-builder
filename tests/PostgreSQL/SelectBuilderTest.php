<?php

declare(strict_types=1);

use Flowpack\QueryObjectBuilder\PostgreSQL\Builder\JsonBuildObjectBuilder;
use Flowpack\QueryObjectBuilder\PostgreSQL\Builder\QueryBuilderException;
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
            WITH
              author_json AS (
                SELECT
                  authors.author_id,
                  json_build_object(
                    'id', authors.author_id,
                    'name', authors.name
                  ) AS json
                FROM
                  authors
              )
            SELECT
              posts.post_id,
              json_build_object(
                'title', posts.title,
                'author', author_json.json
              )
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
                WITH
                  author_books AS (
                    SELECT
                      author_id,
                      COALESCE(
                        json_agg(
                          json_build_object(
                            'Title', books.title,
                            'AuthorID', books.author_id,
                            'PublicationYear', books.publication_year,
                            'CreatedAt', books.created_at,
                            'UpdatedAt', books.updated_at,
                            'ID', books.book_id
                          )
                          ORDER BY
                            publication_year
                        ),
                        '[]'
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
                        json_agg(
                          json_build_object(
                            'GenreID', genres.genre_id,
                            'Name', genres.name
                          )
                          ORDER BY
                            name
                        ),
                        '[]'
                      ) AS genres
                    FROM
                      book_genre
                      JOIN genres USING (genre_id)
                    GROUP BY
                      book_id
                  )
                SELECT
                  json_build_object(
                    'Title', books.title,
                    'AuthorID', books.author_id,
                    'PublicationYear', books.publication_year,
                    'CreatedAt', books.created_at,
                    'UpdatedAt', books.updated_at,
                    'ID', books.book_id,
                    'Author', json_build_object(
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
                  books.book_id = $1
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
                    SELECT
                      json_build_object(
                        'Title', books.title,
                        'AuthorID', books.author_id,
                        'PublicationYear', books.publication_year,
                        'CreatedAt', books.created_at,
                        'UpdatedAt', books.updated_at,
                        'ID', books.book_id,
                        'Author', (
                          SELECT
                            json_build_object(
                              'AuthorID', authors.author_id,
                              'Name', authors.name,
                              'Books', (
                                SELECT
                                  COALESCE(
                                    json_agg(
                                      json_build_object(
                                        'Title', books.title,
                                        'AuthorID', books.author_id,
                                        'PublicationYear', books.publication_year,
                                        'CreatedAt', books.created_at,
                                        'UpdatedAt', books.updated_at,
                                        'ID', books.book_id
                                      )
                                      ORDER BY
                                        books.publication_year
                                    ),
                                    '[]'
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
                              json_agg(
                                json_build_object(
                                    'GenreID', genres.genre_id,
                                    'Name', genres.name
                                )
                                ORDER BY
                                  genres.name
                              ),
                              '[]'
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
                      books.book_id = $1
                    SQL, [2]);
            });

            it('without options', function () use ($selectBook) {
                $q = $selectBook(new BookQueryOpts())->where(Q::n('books.book_id')->eq(Q::arg(2)));

                // language=PostgreSQL
                expect($q)->toRenderSql(<<<'SQL'
                    SELECT
                      json_build_object(
                        'Title', books.title,
                        'AuthorID', books.author_id,
                        'PublicationYear', books.publication_year,
                        'CreatedAt', books.created_at,
                        'UpdatedAt', books.updated_at,
                        'ID', books.book_id
                      )
                    FROM
                      books
                    WHERE
                      books.book_id = $1
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
                    SELECT
                      json_build_object(
                        'Title', books.title,
                        'AuthorID', books.author_id,
                        'PublicationYear', books.publication_year,
                        'ID', books.book_id
                      )
                    FROM
                      books
                    ORDER BY
                      books.publication_year
                    LIMIT
                      10
                    OFFSET
                      $1
                    SQL, [5]);
            });
        });
    });

    // These examples ar taken from https://www.postgresql.org/docs/14/sql-select.html#id-1.9.3.171.9
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

        it('example 2', function () {
            $q = Q::select(Q::n('kind'))
                ->select(Q\Func::sum(Q::n('len')))->as('total')
                ->from(Q::n('films'))
                ->groupBy(Q::n('kind'));

            // language=PostgreSQL
            expect($q)->toRenderSql(<<<'SQL'
                SELECT kind, sum(len) AS total FROM films GROUP BY kind
                SQL, null);
        });

        it('example 3', function () {
            $q = Q::select(Q::n('kind'))
                ->select(Q\Func::sum(Q::n('len')))->as('total')
                ->from(Q::n('films'))
                ->groupBy(Q::n('kind'))
                ->having(Q\Func::sum(Q::n('len'))->lt(Q::interval('5 hours')));

            // language=PostgreSQL
            expect($q)->toRenderSql(<<<'SQL'
                SELECT kind, sum(len) AS total
                FROM films
                GROUP BY kind
                HAVING sum(len) < INTERVAL '5 hours'
                SQL, null);
        });

        describe('example 4', function () {
            it('query 1', function () {
                $q = Q::select(Q::n('*'))
                    ->from(Q::n('distributors'))
                    ->orderBy(Q::n('name'));

                // language=PostgreSQL
                expect($q)->toRenderSql(<<<'SQL'
                    SELECT * FROM distributors ORDER BY name
                    SQL, null);
            });

            it('query 2', function () {
                $q = Q::select(Q::n('*'))
                    ->from(Q::n('distributors'))
                    ->orderBy(Q::int(2));

                // language=PostgreSQL
                expect($q)->toRenderSql(<<<'SQL'
                    SELECT * FROM distributors ORDER BY 2
                    SQL, null);
            });
        });

        it('example 5', function () {
            $q = Q::select(Q::n('distributors.name'))
                ->from(Q::n('distributors'))
                ->where(Q::n('distributors.name')->like(Q::string('W%')))
                ->union()
                ->select(Q::n('actors.name'))
                ->from(Q::n('actors'))
                ->where(Q::n('actors.name')->like(Q::string('W%')));

            // language=PostgreSQL
            expect($q)->toRenderSql(<<<'SQL'
                SELECT distributors.name
                    FROM distributors
                    WHERE distributors.name LIKE 'W%'
                UNION
                SELECT actors.name
                    FROM actors
                    WHERE actors.name LIKE 'W%'
                SQL, null);
        });

        describe('example 6', function () {
            it('query 1', function () {
                $q = Q::select(Q::n('*'))
                    ->from(Q::func('distributors', Q::int(111)));

                // language=PostgreSQL
                expect($q)->toRenderSql(<<<'SQL'
                    SELECT * FROM distributors(111)
                    SQL, null);
            });

            it('query 2', function () {
                $q = Q::select(Q::n('*'))
                    ->from(
                        Q::func('distributors_2', Q::int(111))
                            ->as('d')
                            ->columnDefinition('f1', 'int')
                            ->columnDefinition('f2', 'text'),
                    );

                // language=PostgreSQL
                expect($q)->toRenderSql(<<<'SQL'
                    SELECT * FROM distributors_2(111) AS d (f1 int, f2 text)
                    SQL, null);
            });
        });

        it('example 7', function () {
            $q = Q::select(Q::n('*'))
                ->from(Q\Func::unnest(Q::array(
                    Q::string('a'),
                    Q::string('b'),
                    Q::string('c'),
                    Q::string('d'),
                    Q::string('e'),
                    Q::string('f'),
                ))->withOrdinality());

            // language=PostgreSQL
            expect($q)->toRenderSql(<<<'SQL'
                SELECT * FROM unnest(ARRAY['a','b','c','d','e','f']) WITH ORDINALITY
                SQL, null);
        });

        it('example 7b errors with ORDINALITY and column definitions', function () {
            // To use ORDINALITY together with a column definition list, ROWS FROM( ... ) must be used.
            $q = Q::select(Q::n('*'))
                ->from(Q\Func::unnest(Q::array(
                    Q::string('a'),
                    Q::string('b'),
                ))->withOrdinality()->columnDefinition('x', 'text'));

            expect(static fn () => Q::build($q)->toSql())->toThrow(QueryBuilderException::class);
        });

        it('example 8', function () {
            $q = Q::with('t')->as(
                Q::select(Q::func('random'))->as('x')
                    ->from(Q\Func::generateSeries(Q::int(1), Q::int(3))),
            )
                ->select(Q::n('*'))->from(Q::n('t'))
                ->union()->all()
                ->select(Q::n('*'))->from(Q::n('t'));

            // language=PostgreSQL
            expect($q)->toRenderSql(<<<'SQL'
                WITH t AS (
                    SELECT random() AS x FROM generate_series(1, 3)
                )
                SELECT * FROM t
                UNION ALL
                SELECT * FROM t
                SQL, null);
        });

        it('example 9', function () {
            $q = Q::withRecursive('employee_recursive')->columnNames('distance', 'employee_name', 'manager_name')->as(
                Q::select(Q::int(1), Q::n('employee_name'), Q::n('manager_name'))
                    ->from(Q::n('employee'))
                    ->where(Q::n('manager_name')->eq(Q::string('Mary')))
                    ->union()->all()
                    ->select(Q::n('er.distance')->op('+', Q::int(1)), Q::n('e.employee_name'), Q::n('e.manager_name'))
                    ->from(Q::n('employee_recursive'))->as('er')
                    ->from(Q::n('employee'))->as('e')
                    ->where(Q::n('er.employee_name')->eq(Q::n('e.manager_name'))),
            )
                ->select(Q::n('distance'), Q::n('employee_name'))->from(Q::n('employee_recursive'));

            // language=PostgreSQL
            expect($q)->toRenderSql(<<<'SQL'
                WITH RECURSIVE employee_recursive(distance, employee_name, manager_name) AS (
                    SELECT 1, employee_name, manager_name
                    FROM employee
                    WHERE manager_name = 'Mary'
                  UNION ALL
                    SELECT er.distance + 1, e.employee_name, e.manager_name
                    FROM employee_recursive AS er, employee AS e
                    WHERE er.employee_name = e.manager_name
                  )
                SELECT distance, employee_name FROM employee_recursive
                SQL, null);
        });

        it('example 10', function () {
            $q = Q::select(Q::n('m.name'))->as('mname')->select(Q::n('pname'))
                ->from(Q::n('manufacturers'))->as('m')
                ->fromLateral(Q::func('get_product_names', Q::n('m.id')))->as('pname');

            // language=PostgreSQL
            expect($q)->toRenderSql(<<<'SQL'
                SELECT m.name AS mname, pname
                FROM manufacturers AS m, LATERAL get_product_names(m.id) AS pname
                SQL, null);
        });

        it('example 11', function () {
            $q = Q::select(Q::n('m.name'))->as('mname')->select(Q::n('pname'))
                ->from(Q::n('manufacturers'))->as('m')
                ->leftJoinLateral(Q::func('get_product_names', Q::n('m.id')))->as('pname')->on(Q::bool(true));

            // language=PostgreSQL
            expect($q)->toRenderSql(<<<'SQL'
                SELECT m.name AS mname, pname
                FROM manufacturers AS m LEFT JOIN LATERAL get_product_names(m.id) AS pname ON true
                SQL, null);
        });
    });

    describe('From', function () {
        it('adds tables with a comma', function () {
            $q1 = Q::select(Q::int(1))->from(Q::n('foo'));
            $q2 = $q1->from(Q::n('bar'));

            expect($q1)->toRenderSql('SELECT 1 FROM foo', null);
            expect($q2)->toRenderSql('SELECT 1 FROM foo,bar', null);
        });

        it('adds an only table', function () {
            $q1 = Q::select(Q::int(1))->fromOnly(Q::n('foo'));
            $q2 = $q1->from(Q::n('bar'));

            expect($q1)->toRenderSql('SELECT 1 FROM ONLY foo', null);
            expect($q2)->toRenderSql('SELECT 1 FROM ONLY foo,bar', null);
        });

        it('selects from a subquery', function () {
            $q = Q::select(Q::n('avg_quantity'))->from(
                Q::select(Q\Func::avg(Q::n('quantity')))->as('avg_quantity')->from(Q::n('sales'))->groupBy(Q::n('brand')),
            )->as('t');

            expect($q)->toRenderSql(
                'SELECT avg_quantity FROM (SELECT avg(quantity) AS avg_quantity FROM sales GROUP BY brand) AS t',
                null,
            );
        });

        it('selects from a lateral subquery', function () {
            $q = Q::select(Q::n('avg_quantity'))->fromLateral(
                Q::select(Q\Func::avg(Q::n('quantity')))->as('avg_quantity')->from(Q::n('sales'))->groupBy(Q::n('brand')),
            )->as('t');

            expect($q)->toRenderSql(
                'SELECT avg_quantity FROM LATERAL (SELECT avg(quantity) AS avg_quantity FROM sales GROUP BY brand) AS t',
                null,
            );
        });

        it('selects from rows from', function () {
            $q = Q::select(Q::n('*'))
                ->from(Q::rowsFrom(
                    Q\Func::jsonToRecordset(Q::string('[{"a":40,"b":"foo"},{"a":"100","b":"bar"}]'))
                        ->columnDefinition('a', 'INTEGER')
                        ->columnDefinition('b', 'TEXT'),
                    Q\Func::generateSeries(Q::int(1), Q::int(3)),
                )->withOrdinality())->as('x')->columnAliases('p', 'q', 's')
                ->orderBy(Q::n('p'));

            // language=PostgreSQL
            expect($q)->toRenderSql(<<<'SQL'
                SELECT *
                FROM ROWS FROM
                    (
                        json_to_recordset('[{"a":40,"b":"foo"},{"a":"100","b":"bar"}]')
                            AS (a INTEGER, b TEXT),
                        generate_series(1, 3)
                    ) WITH ORDINALITY AS x (p, q, s)
                ORDER BY p
                SQL, null);
        });
    });

    describe('LeftJoin', function () {
        it('joins immutably', function () {
            $q1 = Q::select(Q::int(1))->from(Q::n('foo'))->leftJoin(Q::n('bar'))->on(Q::n('foo.id')->eq(Q::n('bar.id')));
            $q2 = $q1->leftJoin(Q::n('baz'))->using('id');

            expect($q1)->toRenderSql('SELECT 1 FROM foo LEFT JOIN bar ON foo.id = bar.id', null);
            expect($q2)->toRenderSql('SELECT 1 FROM foo LEFT JOIN bar ON foo.id = bar.id LEFT JOIN baz USING (id)', null);
        });
    });

    describe('CrossJoin', function () {
        it('cross joins immutably', function () {
            $q1 = Q::select(Q::int(1))->from(Q::n('foo'))->crossJoin(Q::n('bar'))->on(Q::n('foo.id')->eq(Q::n('bar.id')));
            $q2 = $q1->crossJoinLateral(Q::n('baz'))->using('id');

            expect($q1)->toRenderSql('SELECT 1 FROM foo CROSS JOIN bar ON foo.id = bar.id', null);
            expect($q2)->toRenderSql('SELECT 1 FROM foo CROSS JOIN bar ON foo.id = bar.id CROSS JOIN LATERAL baz USING (id)', null);
        });
    });

    describe('Select', function () {
        it('selects immutably', function () {
            $q1 = Q::select(Q::int(1));
            $q2 = $q1->select(Q::int(2));

            expect($q1)->toRenderSql('SELECT 1', null);
            expect($q2)->toRenderSql('SELECT 1,2', null);
        });

        it('selects distinct', function () {
            $q = Q::select()->distinct()
                ->select(Q::n('foo'))
                ->from(Q::n('bar'));

            expect($q)->toRenderSql('SELECT DISTINCT foo FROM bar', null);
        });

        it('selects distinct on', function () {
            $q = Q::select()->distinct()->on(Q::n('name'), Q\Func::lower(Q::n('email')))
                ->select(Q::n('foo'))
                ->from(Q::n('bar'));

            // language=PostgreSQL
            expect($q)->toRenderSql(<<<'SQL'
                SELECT DISTINCT ON (name, lower(email)) foo
                FROM bar
                SQL, null);
        });

        it('aliases select expressions immutably', function () {
            $q1 = Q::select()->select(Q::int(1))->as('foo');
            $q2 = $q1->select(Q::int(2))->as('bar');

            expect($q1)->toRenderSql('SELECT 1 AS foo', null);
            expect($q2)->toRenderSql('SELECT 1 AS foo,2 AS bar', null);
        });
    });

    describe('Where', function () {
        it('adds conditions immutably joined with AND', function () {
            $q1 = Q::select(Q::n('foo'))->select()->where(Q::n('is_active')->eq(Q::bool(true)));
            $q2 = $q1->where(Q::n('username')->eq(Q::arg('admin')));

            expect($q1)->toRenderSql('SELECT foo WHERE is_active = true', null);
            expect($q2)->toRenderSql('SELECT foo WHERE is_active = true AND username = $1', ['admin']);
        });

        it('where exists', function () {
            $q = Q::select(Q::n('col1'))
                ->from(Q::n('tab1'))
                ->where(Q::exists(
                    Q::select(Q::int(1))->from(Q::n('tab2'))->where(Q::n('col2')->eq(Q::n('tab1.col2'))),
                ));

            // language=PostgreSQL
            expect($q)->toRenderSql(<<<'SQL'
                SELECT col1
                FROM tab1
                WHERE EXISTS (SELECT 1 FROM tab2 WHERE col2 = tab1.col2)
                SQL, null);
        });

        it('where not exists', function () {
            $q = Q::select(Q::n('col1'))
                ->from(Q::n('tab1'))
                ->where(Q::not(Q::exists(
                    Q::select(Q::int(1))->from(Q::n('tab2'))->where(Q::n('col2')->eq(Q::n('tab1.col2'))),
                )));

            // language=PostgreSQL
            expect($q)->toRenderSql(<<<'SQL'
                SELECT col1
                FROM tab1
                WHERE NOT EXISTS (SELECT 1 FROM tab2 WHERE col2 = tab1.col2)
                SQL, null);
        });

        it('where in args', function () {
            $ids = [1, 2, 3];

            $q = Q::select(Q::n('username'))
                ->from(Q::n('accounts'))
                ->where(Q::n('id')->in(Q::args(...$ids)));

            // language=PostgreSQL
            expect($q)->toRenderSql(<<<'SQL'
                SELECT username
                FROM accounts
                WHERE id IN ($1, $2, $3)
                SQL, [1, 2, 3]);
        });

        it('where in exps', function () {
            $q = Q::select(Q::n('username'))
                ->from(Q::n('accounts'))
                ->where(Q::n('id')->in(Q::exps(Q::int(42), Q::string('abc'))));

            // language=PostgreSQL
            expect($q)->toRenderSql(<<<'SQL'
                SELECT username
                FROM accounts
                WHERE id IN (42, 'abc')
                SQL, null);
        });

        it('where with negated junction', function () {
            $q = Q::select(Q::n('*'))
                ->from(Q::n('accounts'))
                ->where(Q::not(Q::and(
                    Q::n('is_active')->eq(Q::bool(true)),
                    Q::n('username')->eq(Q::arg('admin')),
                )));

            // language=PostgreSQL
            expect($q)->toRenderSql(<<<'SQL'
                SELECT *
                FROM accounts
                WHERE NOT (is_active = true AND username = $1)
                SQL, ['admin']);
        });

        it('where with negated comparison', function () {
            $q = Q::select(Q::n('*'))
                ->from(Q::n('accounts'))
                ->where(Q::not(Q::n('is_active')->eq(Q::bool(true))));

            // language=PostgreSQL
            expect($q)->toRenderSql(<<<'SQL'
                SELECT *
                FROM accounts
                WHERE NOT is_active = true
                SQL, null);
        });

        it('where with equal is not null', function () {
            $isActive = true;

            $q = Q::select(Q::n('*'))
                ->from(Q::n('accounts'))
                ->where(Q::arg($isActive)->eq(Q::n('deactivated_at')->isNull()));

            // language=PostgreSQL
            expect($q)->toRenderSql(<<<'SQL'
                SELECT *
                FROM accounts
                WHERE $1 = (deactivated_at IS NULL)
                SQL, [true]);
        });

        it('where all with subselect', function () {
            $q = Q::select(Q::n('*'))
                ->from(Q::n('employees'))
                ->where(Q::n('salary')->gt(Q::all(Q::select(Q::n('salary'))->from(Q::n('managers')))));

            // language=PostgreSQL
            expect($q)->toRenderSql(<<<'SQL'
                SELECT *
                FROM employees
                WHERE salary > ALL (SELECT salary FROM managers)
                SQL, null);
        });

        it('where any with array', function () {
            $q = Q::select(Q::n('*'))
                ->from(Q::n('table'))
                ->where(Q::n('column')->eq(Q::any(Q::array(Q::int(1), Q::int(2), Q::int(3)))));

            // language=PostgreSQL
            expect($q)->toRenderSql(<<<'SQL'
                SELECT *
                FROM "table"
                WHERE "column" = ANY (ARRAY[1, 2, 3])
                SQL, null);
        });
    });

    describe('GroupBy', function () {
        it('empty', function () {
            $q = Q::select(Q\Func::sum(Q::n('y')))
                ->from(Q::n('test1'))
                ->groupBy()
                ->empty();

            expect($q)->toRenderSql('SELECT sum(y) FROM test1 GROUP BY ()', null);
        });

        it('single', function () {
            $q = Q::select(Q::n('x'), Q\Func::sum(Q::n('y')))
                ->from(Q::n('test1'))
                ->groupBy(Q::n('x'));

            expect($q)->toRenderSql('SELECT x,sum(y) FROM test1 GROUP BY x', null);
        });

        it('multiple', function () {
            $q = Q::select(Q::n('product_id'), Q::n('p.name'))
                ->select(Q\Func::sum(Q::n('s.units'))->op('*', Q::n('p.price')))->as('sales')
                ->from(Q::n('products'))->as('p')
                ->leftJoin(Q::n('sales'))->as('s')->using('product_id')
                ->groupBy(Q::n('product_id'), Q::n('p.name'), Q::n('p.price'));

            expect($q)->toRenderSql(
                'SELECT product_id,p.name,sum(s.units) * p.price AS sales FROM products AS p LEFT JOIN sales AS s USING (product_id) GROUP BY (product_id,p.name,p.price)',
                null,
            );
        });

        it('rollup', function () {
            $q = Q::select(Q::n('a'), Q::n('b'), Q::n('c'), Q::n('d'))
                ->from(Q::n('test1'))
                ->groupBy()
                ->rollup(
                    Q::exps(Q::n('a')),
                    Q::exps(Q::n('b'), Q::n('c')),
                    Q::exps(Q::n('d')),
                );

            expect($q)->toRenderSql('SELECT a,b,c,d FROM test1 GROUP BY ROLLUP (a,(b,c),d)', null);
        });

        it('distinct rollup', function () {
            $q = Q::select(Q::n('a'), Q::n('b'), Q::n('c'))
                ->from(Q::n('test1'))
                ->groupBy()->distinct()
                ->rollup(Q::exps(Q::n('a'), Q::n('b')))
                ->rollup(Q::exps(Q::n('a'), Q::n('c')));

            expect($q)->toRenderSql('SELECT a,b,c FROM test1 GROUP BY DISTINCT ROLLUP (a, b), ROLLUP (a, c)', null);
        });

        it('cube', function () {
            $q = Q::select(Q::n('a'), Q::n('b'), Q::n('c'), Q::n('d'))
                ->from(Q::n('test1'))
                ->groupBy()
                ->cube(
                    Q::exps(Q::n('a'), Q::n('b')),
                    Q::exps(Q::n('c'), Q::n('d')),
                );

            expect($q)->toRenderSql('SELECT a,b,c,d FROM test1 GROUP BY CUBE ((a,b),(c,d))', null);
        });

        it('grouping sets', function () {
            $q = Q::select(Q::n('brand'), Q::n('size'), Q\Func::sum(Q::n('sales')))
                ->from(Q::n('items_sold'))
                ->groupBy()
                ->groupingSets(
                    Q::exps(Q::n('brand')),
                    Q::exps(Q::n('size')),
                    Q::exps(),
                );

            expect($q)->toRenderSql('SELECT brand,size,sum(sales) FROM items_sold GROUP BY GROUPING SETS (brand,size,())', null);
        });
    });

    describe('OrderBy', function () {
        it('orders immutably', function () {
            $q1 = Q::select(Q::n('foo'))->orderBy(Q::n('foo'))->desc();
            $q2 = $q1->select(Q::n('bar'))->orderBy(Q::n('bar'))->asc()->nullsLast();

            expect($q1)->toRenderSql('SELECT foo ORDER BY foo DESC', null);
            expect($q2)->toRenderSql('SELECT foo,bar ORDER BY foo DESC,bar ASC NULLS LAST', null);
        });
    });

    describe('With', function () {
        it('appends with queries immutably', function () {
            $q1 = Q::with('foo')->as(Q::select(Q::int(1)))->select(Q::n('foo'));
            $q2 = $q1->appendWith(Q::with('bar')->as(Q::select(Q::int(2))));

            expect($q1)->toRenderSql('WITH foo AS (SELECT 1) SELECT foo', null);
            expect($q2)->toRenderSql('WITH foo AS (SELECT 1),bar AS (SELECT 2) SELECT foo', null);
        });

        it('materialized', function () {
            $q = Q::with('foo')->asMaterialized(Q::select(Q::int(1)))->select(Q::n('foo'));

            expect($q)->toRenderSql('WITH foo AS MATERIALIZED (SELECT 1) SELECT foo', null);
        });

        it('not materialized', function () {
            $q = Q::with('foo')->asNotMaterialized(Q::select(Q::int(1)))->select(Q::n('foo'));

            expect($q)->toRenderSql('WITH foo AS NOT MATERIALIZED (SELECT 1) SELECT foo', null);
        });

        it('multiple recursive', function () {
            $q = Q::with('foo')->as(Q::select(Q::int(1)))
                ->withRecursive('bar')->as(Q::select(Q::int(2)))
                ->select(Q::n('foo'));

            expect($q)->toRenderSql('WITH RECURSIVE foo AS (SELECT 1),bar AS (SELECT 2) SELECT foo', null);
        });

        it('recursive with search depth', function () {
            $q = Q::withRecursive('search_tree')->columnNames('id', 'link', 'data')->as(
                Q::select(Q::n('t.id'), Q::n('t.link'), Q::n('t.data'))
                    ->from(Q::n('tree'))->as('t')
                    ->union()->all()
                    ->select(Q::n('t.id'), Q::n('t.link'), Q::n('t.data'))
                    ->from(Q::n('tree'))->as('t')
                    ->from(Q::n('search_tree'))->as('st')
                    ->where(Q::n('t.id')->eq(Q::n('st.link'))),
            )->searchDepthFirst()->by(Q::n('id'))->set('ordercol')
                ->select(Q::n('*'))->from(Q::n('search_tree'))->orderBy(Q::n('ordercol'));

            // language=PostgreSQL
            expect($q)->toRenderSql(<<<'SQL'
                WITH RECURSIVE search_tree(id, link, data) AS (
                    SELECT t.id, t.link, t.data
                    FROM tree AS t
                  UNION ALL
                    SELECT t.id, t.link, t.data
                    FROM tree AS t, search_tree AS st
                    WHERE t.id = st.link
                ) SEARCH DEPTH FIRST BY id SET ordercol
                SELECT * FROM search_tree ORDER BY ordercol
                SQL, null);
        });
    });

    describe('For', function () {
        it('for update', function () {
            $q = Q::select(Q::n('foo'))->from(Q::n('bar'))->forUpdate();

            expect($q)->toRenderSql('SELECT foo FROM bar FOR UPDATE', null);
        });

        it('for key share of table1, table2 skip locked', function () {
            $q = Q::select(Q::n('foo'))->from(Q::n('bar'))->forKeyShare()->of('table1', 'table2')->skipLocked();

            expect($q)->toRenderSql('SELECT foo FROM bar FOR KEY SHARE OF table1,table2 SKIP LOCKED', null);
        });
    });

    describe('Combinations', function () {
        it('EXCEPT and UNION', function () {
            $q = Q::select(Q::n('*'))
                ->from(Q::n('input_data'))
                ->except()->all()->query(
                    Q::select(Q::n('*'))->from(Q::n('input_not_exists'))
                        ->union()->all()
                        ->select(Q::n('*'))->from(Q::n('input_alternative_already_exists')),
                );

            // language=PostgreSQL
            expect($q)->toRenderSql(<<<'SQL'
                SELECT *
                    FROM input_data
                    EXCEPT ALL
                    (SELECT * FROM input_not_exists UNION ALL SELECT * FROM input_alternative_already_exists)
                SQL, null);
        });
    });

    describe('IsEmpty', function () {
        it('is empty for a fresh builder', function () {
            expect(Q::select()->isEmpty())->toBeTrue();
        });

        it('is not empty once it has content', function () {
            expect(Q::select(Q::n('foo'))->from(Q::n('bar'))->isEmpty())->toBeFalse();
        });
    });
});
