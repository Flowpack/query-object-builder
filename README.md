# Query Object Builder

[![Latest Stable Version](https://img.shields.io/packagist/v/flowpack/query-object-builder.svg)](https://packagist.org/packages/flowpack/query-object-builder)
[![PHP Version Require](https://img.shields.io/packagist/php-v/flowpack/query-object-builder.svg)](https://packagist.org/packages/flowpack/query-object-builder)
[![CI](https://github.com/Flowpack/query-object-builder/actions/workflows/ci.yml/badge.svg)](https://github.com/Flowpack/query-object-builder/actions/workflows/ci.yml)
[![License](https://img.shields.io/packagist/l/flowpack/query-object-builder.svg)](LICENSE)

A fluent, immutable, fully-typed SQL query builder for PHP 8.4+.

You compose a query from small, type-safe expression objects and render it to a
parameterized SQL string with bound arguments — never by concatenating strings.
The package ships **two dialect families**, each modelling *its own* SQL rather
than a lowest-common-denominator subset:

- **`PostgreSQL\Q`** — the PostgreSQL builder.
- **`MySQL\Q`** — a single **MySQL-family** builder covering both **MySQL** *and*
  **MariaDB**. Every construct is buildable regardless of engine or version; where
  the two engines diverge you build the engine's own form, and an opt-in
  [target-validation](#mysql--mariadb-one-builder-two-engines) pass reports any
  construct the engine (and version) you are targeting cannot express.

Both families share the same design — fluent, immutable, type-state builders and
the `Q` / `Q\Func` facade split — so once you know one, you know the other.

## Contents

- [Why Query Object Builder?](#why-query-object-builder)
- [Requirements](#requirements)
- [Installation](#installation)
- [The two dialect families](#the-two-dialect-families)
- [Quick start](#quick-start)
- [Core concepts](#core-concepts)
- [MySQL & MariaDB: one builder, two engines](#mysql--mariadb-one-builder-two-engines)
- [Examples](#examples)
- [Parameters](#parameters)
- [Validation & errors](#validation--errors)
- [Executing queries](#executing-queries)
- [Best practices](#best-practices)
- [Development](#development)
- [License](#license)

## Why Query Object Builder?

- **Dialect-native, not lowest-common-denominator** — each family's facade and
  builders model that engine's own SQL (PostgreSQL arrays and `ON CONFLICT`;
  MySQL/MariaDB `JSON_TABLE`, `ON DUPLICATE KEY UPDATE`, backtick quoting). No
  feature is dropped to fit a shared subset.
- **JSON-first** — first-class support for building hierarchical data directly in
  the database (`json_build_object` / `json_agg` on PostgreSQL, `JSON_OBJECT` /
  `JSON_ARRAYAGG` on MySQL/MariaDB).
- **Complete feature set** — CTEs, window functions and frames, grouping,
  subqueries, upserts, `RETURNING`, set-returning / table functions, and more.
- **Type-safe** — builder methods only expose what is valid in the current
  context, so invalid queries are hard to express.
- **Immutable** — every builder method returns a new instance, so base queries
  can be shared and specialised without surprises.
- **Runtime target validation** — the MySQL family renders both engines from one
  builder and can *report* (never silently rewrite) any construct a specific
  engine or version cannot express.
- **Zero runtime dependencies** — requires only PHP 8.4+. Everything else is a
  dev-only dependency (PHPUnit, Pest, PHPStan).

## Requirements

- PHP 8.4 or newer

## Installation

```bash
composer require flowpack/query-object-builder
```

## The two dialect families

Pick the facade for your database; the fluent API is the same shape on both.

|                     | PostgreSQL                                   | MySQL / MariaDB                                          |
|---------------------|----------------------------------------------|---------------------------------------------------------|
| Import              | `Flowpack\QueryObjectBuilder\PostgreSQL\Q`   | `Flowpack\QueryObjectBuilder\MySQL\Q`                   |
| Placeholders        | numbered `$1`, `$2`, …                        | positional `?`                                          |
| Identifier quoting  | `"col"` (when needed)                        | `` `col` `` (when needed)                               |
| Boolean literal     | `true` / `false`                             | `TRUE` / `FALSE`                                         |
| Function casing     | `count(*)`, `json_agg(...)`                   | `COUNT(*)`, `JSON_ARRAYAGG(...)`                         |
| Cast                | `expr::type`                                  | `CAST(expr AS type)` / `CONVERT(...)`                    |
| Engines             | PostgreSQL                                    | MySQL **and** MariaDB (one builder)                     |

`PostgreSQL\Q` and `MySQL\Q` do **not** share types — a query built with one is
rendered by its own `QueryBuilder`.

## Quick start

**PostgreSQL:**

```php
use Flowpack\QueryObjectBuilder\PostgreSQL\Q;

$q = Q::select(Q::n('name'), Q::n('email'))
    ->from(Q::n('users'))
    ->where(Q::n('active')->eq(Q::arg(true)))
    ->orderBy(Q::n('name'));

[$sql, $args] = Q::build($q)->toSql();

echo $sql;        // SELECT name,email FROM users WHERE active = $1 ORDER BY name
var_dump($args);  // [true]
```

**MySQL / MariaDB:**

```php
use Flowpack\QueryObjectBuilder\MySQL\Q;

$q = Q::select(Q::n('name'), Q::n('email'))
    ->from(Q::n('users'))
    ->where(Q::n('active')->eq(Q::arg(true)))
    ->orderBy(Q::n('name'));

[$sql, $args] = Q::build($q)->toSql();

echo $sql;        // SELECT name,email FROM users WHERE active = ? ORDER BY name
var_dump($args);  // [true]
```

`Q::build($q)->toSql()` returns a `[$sql, $args]` pair: a SQL string with the
dialect's placeholders and the positional argument list to bind. See
[Executing queries](#executing-queries) for how to run it.

## Core concepts

### The `Q` facade

`Q` is the single entry point for building queries. It exposes the builder
package as static factory methods so you never reference the underlying builder
types directly:

- **Statements**: `Q::select()`, `Q::insertInto()`, `Q::update()`,
  `Q::deleteFrom()`, `Q::with()`, `Q::withRecursive()` (MySQL family adds
  `Q::replaceInto()`).
- **Identifiers**: `Q::n('table.column')` for names/columns.
- **Literals**: `Q::string()`, `Q::int()`, `Q::float()`, `Q::bool()`,
  `Q::null()`, `Q::default()` (PostgreSQL adds `Q::array()`, `Q::interval()`).
- **Parameters**: `Q::arg()` (positional), `Q::bind()` (named).
- **Composition**: `Q::and()`, `Q::or()`, `Q::not()`, `Q::exists()`, `Q::any()`,
  `Q::all()`, `Q::case()`, `Q::coalesce()`, `Q::func()`.

### The `Q\Func` facade

SQL functions live on `Q\Func`. On PostgreSQL:
`Q\Func::jsonBuildObject()`, `Q\Func::jsonAgg()`, `Q\Func::count()`,
`Q\Func::rowNumber()`, `Q\Func::unnest()`, … On MySQL/MariaDB:
`Q\Func::jsonObject()`, `Q\Func::jsonArrayAgg()`, `Q\Func::count()`,
`Q\Func::groupConcat()`, `Q\Func::rank()`, … It is named `Func` (not `Fn`)
because `fn` is a reserved keyword in PHP.

`Q\Func` is the *expression* facade: every method returns something usable
anywhere an expression is valid. Constructs that are not general expressions — a
statement, or a FROM-only producer like `JSON_TABLE` — live on `Q` instead
(`Q::jsonTable()`, PostgreSQL's `Q::rowsFrom()`).

### Immutability

Every builder method returns a **new** builder — the original is never mutated.
This makes base queries safe to reuse:

```php
$base = Q::select(Q::n('*'))->from(Q::n('users'));

$active = $base->where(Q::n('active')->eq(Q::bool(true)));
$recent = $base->where(Q::n('created_at')->gt(Q::string('2024-01-01')));
// $base is unchanged
```

### Operators on expressions

Expressions returned by `Q::n()`, `Q::arg()`, literals and functions carry the
SQL **operators** as fluent methods; what reads as a **function** is built
through the facade. Each family models the operator set its dialect actually has:

- **Shared**: `->eq()`, `->neq()`, `->lt()`, `->lte()`, `->gt()`, `->gte()`,
  `->like()`, `->in()`, `->isNull()`, `->isNotNull()`, `->plus()`, `->minus()`,
  `->mult()`, `->and()`/`->or()`, …
- **PostgreSQL-specific**: `->ilike()`, `->cast('text')` (`::`), `->concat()`
  (`||`), array/JSON operators.
- **MySQL/MariaDB-specific**: `->nullSafeEq()` (`<=>`), `->regexp()`,
  `->memberOf()` (`MEMBER OF`), `->jsonExtract()` / `->jsonExtractText()`
  (`->` / `->>`, MySQL), the bitwise operators (`->bitAnd()`, `->bitOr()`,
  `->shiftLeft()`, …).

Parentheses are added automatically based on each dialect's operator precedence.

### Building and rendering

A finished query is handed to `Q::build($q)` to configure rendering, then
`->toSql()` produces the `[$sql, $args]` pair:

```php
[$sql, $args] = Q::build($q)->toSql();                       // validate + render
[$sql, $args] = Q::build($q)->withoutValidation()->toSql();  // skip value checks
[$sql, $args] = Q::build($q)->withNamedArgs([...])->toSql();  // bind Q::bind() names
```

See [Validation & errors](#validation--errors) for what is checked and how to
opt out, and the MySQL-only [target validation](#opt-in-target-validation) pass.

## MySQL & MariaDB: one builder, two engines

The MySQL family is a **single builder** for both MySQL and MariaDB. The two
engines share ~95% of their grammar and *all* of their rendering conventions
(backtick identifiers, `?` placeholders, string escaping), so one builder models
both. This section is the design goal that makes that safe.

**Nothing is gated at build time.** Every construct is buildable regardless of
engine or version — the builder never refuses a feature because your engine is
the wrong flavour or too old. Engine and version are inputs to the *opt-in*
validation pass below, which *reports* (never rewrites or blocks) what a target
cannot express. This is also why the PostgreSQL builder carries no version: it
models a single engine, so it needs no target at all.

### Rendering is determined by construction

Rendering **never branches on a dialect flag**. What you build is exactly what is
rendered — so the engine-divergent constructs are reached by *building the
engine's own form*, not by toggling a mode:

| Intent                    | MySQL                                          | MariaDB                                       |
|---------------------------|------------------------------------------------|-----------------------------------------------|
| Shared row lock           | `->forShare()`                                 | `->lockInShareMode()`                         |
| Upsert proposed-row ref   | `->as('new')` + `Q::n('new.col')`              | `Q::values('col')` *(also works on MySQL)*    |
| JSON path                 | `->jsonExtract()` / `->jsonExtractText()`      | `Q\Func::jsonExtract()` / `Q\Func::jsonUnquote()` |
| Pretty-print JSON         | `Q\Func::jsonPretty()`                         | `Q\Func::jsonDetailed()`                      |
| `RETURNING`               | — (not supported)                              | `->returning(...)`                            |
| `LATERAL`                 | `->joinLateral()` / `->fromLateral()`          | — (no equivalent)                             |

Building without a target renders precisely what you constructed and never fails
on dialect grounds.

### Opt-in target validation

To check a query against a specific engine (and, optionally, version), opt in
with `withValidateTarget()`. Each divergent construct reports itself while
rendering, so you get a `QueryBuilderException` naming what the target cannot
express:

```php
use Flowpack\QueryObjectBuilder\MySQL\Q;
use Flowpack\QueryObjectBuilder\MySQL\Builder\Target;

$q = Q::select(Q::n('id'))->from(Q::n('t'))->forShare();   // FOR SHARE is MySQL-only

Q::build($q)->withValidateTarget(Target::mysql())->toSql();    // ok
Q::build($q)->withValidateTarget(Target::mariaDb())->toSql();  // throws QueryBuilderException:
// "FOR SHARE requires MySQL, but the query is validated against MariaDB"
```

`Target::mysql($version)` / `Target::mariaDb($version)` carry an optional
version. Version-gated features are checked only when a version is supplied — a
leading `WITH` on `UPDATE`/`DELETE` is valid on MySQL and on MariaDB 12.3+, so it
passes against `Target::mariaDb('12.3')` but fails against `Target::mariaDb('11.4')`.
A target with no version only checks the dialect.

Worked per-engine variants for each divergent construct are in the
[Examples](#examples) below.

## Examples

> **How to read these examples.** Unless a snippet is labelled for a specific
> engine, the PHP builds **identically on both facades** — import `PostgreSQL\Q`
> or `MySQL\Q` as `Q`. Only the rendered SQL differs by dialect (`$1` vs `?`,
> identifier quoting, `true` vs `TRUE`, lower- vs upper-case function names).
> Divergent constructs show a snippet per engine. The builder emits compact,
> single-line SQL; the SQL below is formatted for readability.

**Jump to:**

- [Basic queries](#basic-queries) ·
  [Joins](#joins) ·
  [Aggregation & grouping](#aggregation--grouping) ·
  [Window functions](#window-functions) ·
  [JSON](#json) ·
  [Arrays (PostgreSQL)](#arrays-postgresql) ·
  [Subqueries](#subqueries) ·
  [CTEs (WITH)](#ctes-with) ·
  [INSERT & upsert](#insert--upsert) ·
  [UPDATE](#update) ·
  [DELETE](#delete) ·
  [Functions & operators](#functions--operators) ·
  [Locking](#locking)

### Basic queries

#### SELECT with WHERE

```php
$q = Q::select(Q::n('name'), Q::n('email'))
    ->from(Q::n('users'))
    ->where(Q::n('active')->eq(Q::arg(true)));
```

```sql
-- PostgreSQL
SELECT name, email FROM users WHERE active = $1   -- args: [true]
-- MySQL / MariaDB
SELECT name, email FROM users WHERE active = ?    -- args: [true]
```

#### Multiple conditions

```php
$q = Q::select(Q::n('*'))
    ->from(Q::n('employees'))
    ->where(Q::and(
        Q::or(
            Q::n('firstname')->like(Q::arg('John%')),
            Q::n('lastname')->like(Q::arg('John%')),
        ),
        Q::n('active')->eq(Q::bool(true)),
    ));
```

```sql
-- PostgreSQL
SELECT * FROM employees
WHERE (firstname LIKE $1 OR lastname LIKE $2) AND active = true
-- MySQL / MariaDB
SELECT * FROM employees
WHERE (firstname LIKE ? OR lastname LIKE ?) AND active = TRUE
```

> PostgreSQL also has `->ilike()` for case-insensitive matching; MySQL/MariaDB
> use `->like()` (case-insensitivity follows the column collation) or `->regexp()`.

#### DISTINCT

```php
$q = Q::select(Q::n('department'))->distinct()->from(Q::n('employees'));
```

```sql
-- PostgreSQL / MySQL / MariaDB
SELECT DISTINCT department FROM employees
```

#### ORDER BY, LIMIT and OFFSET

```php
$q = Q::select(Q::n('name'), Q::n('salary'))
    ->from(Q::n('employees'))
    ->orderBy(Q::n('salary'))->desc()
    ->limit(Q::int(10))
    ->offset(Q::int(20));
```

```sql
-- PostgreSQL / MySQL / MariaDB
SELECT name, salary FROM employees ORDER BY salary DESC LIMIT 10 OFFSET 20
```

> `NULLS FIRST` / `NULLS LAST` (`->nullsLast()`) is PostgreSQL-only.

### Joins

`join()`, `leftJoin()`, `rightJoin()`, `crossJoin()` are shared; alias with
`->as()` and constrain with `->on(...)` or `->using('col')`.

```php
$q = Q::select(Q::n('u.name'), Q::n('p.title'))
    ->from(Q::n('users'))->as('u')
    ->leftJoin(Q::n('posts'))->as('p')->on(Q::n('u.id')->eq(Q::n('p.user_id')));
```

```sql
-- PostgreSQL / MySQL / MariaDB
SELECT u.name, p.title FROM users AS u
LEFT JOIN posts AS p ON u.id = p.user_id
```

#### LATERAL join

Supported by PostgreSQL and MySQL — MariaDB has no `LATERAL`.

```php
$q = Q::select(Q::n('*'))
    ->from(Q::n('orders'))->as('o')
    ->joinLateral(
        Q::select(Q::n('*'))->from(Q::n('items'))->as('i')
            ->where(Q::n('i.order_id')->eq(Q::n('o.id')))
            ->limit(Q::int(3)),
    )->as('top')->on(Q::bool(true));
```

```sql
-- PostgreSQL
SELECT * FROM orders AS o
JOIN LATERAL (SELECT * FROM items AS i WHERE i.order_id = o.id LIMIT 3) AS top ON true
-- MySQL
SELECT * FROM orders AS o
JOIN LATERAL (SELECT * FROM items AS i WHERE i.order_id = o.id LIMIT 3) AS top ON TRUE
```

`fromLateral()`, `leftJoinLateral()` and `crossJoinLateral()` are also available.
Within the MySQL family, `LATERAL` is MySQL-only — validating against
`Target::mariaDb()` reports *"LATERAL requires MySQL"*.

### Aggregation & grouping

```php
$q = Q::select(Q::n('department'), Q\Func::count(Q::n('*')))->as('n')
    ->from(Q::n('employees'))
    ->groupBy(Q::n('department'))
    ->having(Q\Func::count(Q::n('*'))->gt(Q::int(5)));
```

```sql
-- PostgreSQL
SELECT department, count(*) AS n FROM employees
GROUP BY department HAVING count(*) > 5
-- MySQL / MariaDB
SELECT department, COUNT(*) AS n FROM employees
GROUP BY department HAVING COUNT(*) > 5
```

#### ROLLUP

The engines spell super-aggregate grouping differently:

```php
// PostgreSQL: GROUP BY ROLLUP (...)
$q = Q::select(Q::n('department'), Q::n('job_title'), Q\Func::sum(Q::n('salary')))
    ->from(Q::n('employees'))
    ->groupBy()->rollup(Q::exps(Q::n('department')), Q::exps(Q::n('job_title')));
// SELECT department, job_title, sum(salary) FROM employees
// GROUP BY ROLLUP (department, job_title)

// MySQL / MariaDB: GROUP BY ... WITH ROLLUP
$q = Q::select(Q::n('department'), Q::n('job_title'), Q\Func::sum(Q::n('salary')))
    ->from(Q::n('employees'))
    ->groupBy(Q::n('department'), Q::n('job_title'))->withRollup();
// SELECT department, job_title, SUM(salary) FROM employees
// GROUP BY department, job_title WITH ROLLUP
```

> PostgreSQL also supports `->groupingSets(...)` and `->cube(...)`.

### Window functions

Aggregate and window functions carry `->over()` (inline) or `->over('w')`
(named), refined with `->partitionBy(...)`, `->orderBy(...)` and frame clauses.

```php
$q = Q::select(
    Q::n('name'),
    Q::n('salary'),
    Q\Func::rowNumber()->over()->partitionBy(Q::n('department'))->orderBy(Q::n('salary'))->desc(),
)->from(Q::n('employees'));
```

```sql
-- PostgreSQL
SELECT name, salary,
       row_number() OVER (PARTITION BY department ORDER BY salary DESC)
FROM employees
-- MySQL / MariaDB
SELECT name, salary,
       ROW_NUMBER() OVER (PARTITION BY department ORDER BY salary DESC)
FROM employees
```

#### Named windows

```php
$q = Q::select(
    Q\Func::sum(Q::n('salary'))->over('w'),
    Q\Func::avg(Q::n('salary'))->over('w'),
)
    ->from(Q::n('empsalary'))
    ->window('w')->as()->partitionBy(Q::n('depname'))->orderBy(Q::n('salary'))->desc();
```

```sql
-- MySQL / MariaDB (PostgreSQL renders the same, lower-cased)
SELECT SUM(salary) OVER w, AVG(salary) OVER w
FROM empsalary
WINDOW w AS (PARTITION BY depname ORDER BY salary DESC)
```

#### Frames

```php
use Flowpack\QueryObjectBuilder\MySQL\Q;

// Running total: ROWS UNBOUNDED PRECEDING
$q = Q::select(
    Q\Func::sum(Q::n('val'))->over()
        ->partitionBy(Q::n('subject'))->orderBy(Q::n('time'))
        ->rows(Q::unboundedPreceding()),
)->from(Q::n('observations'));
// SELECT SUM(val) OVER (PARTITION BY subject ORDER BY time ROWS UNBOUNDED PRECEDING) FROM observations

// Moving average: ROWS BETWEEN 1 PRECEDING AND 1 FOLLOWING
$q = Q::select(
    Q\Func::avg(Q::n('val'))->over()
        ->partitionBy(Q::n('subject'))->orderBy(Q::n('time'))
        ->rows(Q::preceding(Q::int(1)), Q::following(Q::int(1))),
)->from(Q::n('observations'));
// SELECT AVG(val) OVER (PARTITION BY subject ORDER BY time ROWS BETWEEN 1 PRECEDING AND 1 FOLLOWING) FROM observations
```

> MariaDB additionally offers distribution aggregates — `Q\Func::median()`,
> `Q\Func::percentileCont()` / `percentileDisc()` with `->withinGroup()` — which
> validate against `Target::mariaDb()` only.

### JSON

Both families build hierarchical data in the database, but with each engine's own
function set.

#### Build a JSON object

Both families build objects from key/value properties with a `->prop()` builder,
under each dialect's own function name:

```php
// PostgreSQL: json_build_object()
$q = Q::select(
    Q\Func::jsonBuildObject()
        ->prop('id', Q::n('id'))
        ->prop('name', Q::n('name')),
)->from(Q::n('users'));
// SELECT json_build_object('id', id, 'name', name) FROM users

// MySQL / MariaDB: JSON_OBJECT()
$q = Q::select(
    Q\Func::jsonObject()
        ->prop('id', Q::n('id'))
        ->prop('name', Q::n('name')),
)->from(Q::n('users'));
// SELECT JSON_OBJECT('id', id, 'name', name) FROM users
```

The builder keeps insertion order, and `->propIf($cond, 'key', $value)` /
`->applyIf(...)` / `->unset('key')` let you shape the object incrementally:

```php
$obj = Q\Func::jsonObject()
    ->prop('id', Q::n('id'))
    ->propIf($includeName, 'name', Q::n('name'));
```

Property keys are string literals; for a computed key, drop to the
`Q::func('json_build_object'|'JSON_OBJECT', ...)` escape hatch.

#### JSON-first query (`selectJson`)

When a query's primary output is a single JSON object, `Q::selectJson($obj)`
makes it the first select element; refine it later with `applySelectJson()` and
name it with `->as()`. Both families support it — pass the family's own object
builder.

```php
$q = Q::selectJson(
    // PostgreSQL: Q\Func::jsonBuildObject() — MySQL / MariaDB: Q\Func::jsonObject()
    Q\Func::jsonObject()
        ->prop('id', Q::n('authors.author_id'))
        ->prop('name', Q::n('authors.name')),
)
    ->from(Q::n('authors'))
    ->where(Q::n('authors.author_id')->eq(Q::arg(123)));

// The builder is a blueprint — add to the JSON selection later:
$q = $q->applySelectJson(fn ($obj) => $obj->prop('postCount', Q\Func::count(Q::n('posts'))));
```

```sql
-- PostgreSQL
SELECT json_build_object('id', authors.author_id, 'name', authors.name, 'postCount', count(posts))
FROM authors WHERE authors.author_id = $1
-- MySQL / MariaDB
SELECT JSON_OBJECT('id', authors.author_id, 'name', authors.name, 'postCount', COUNT(posts))
FROM authors WHERE authors.author_id = ?
```

#### Aggregate rows into a JSON array

```php
// PostgreSQL: json_agg(...)
$q = Q::select(
    Q::n('department'),
    Q\Func::jsonAgg(
        Q\Func::jsonBuildObject()->prop('name', Q::n('name'))->prop('salary', Q::n('salary')),
    )->orderBy(Q::n('name')),
)->from(Q::n('employees'))->groupBy(Q::n('department'));
// SELECT department, json_agg(json_build_object('name', name, 'salary', salary) ORDER BY name)
// FROM employees GROUP BY department

// MySQL / MariaDB: JSON_ARRAYAGG(...); COALESCE with JSON_ARRAY() to avoid NULL on empty sets
$q = Q::select(
    Q::n('department'),
    Q::coalesce(
        Q\Func::jsonArrayAgg(
            Q\Func::jsonObject()->prop('name', Q::n('name'))->prop('salary', Q::n('salary')),
        ),
        Q\Func::jsonArray(),
    ),
)->from(Q::n('employees'))->groupBy(Q::n('department'));
// SELECT department, COALESCE(JSON_ARRAYAGG(JSON_OBJECT('name', name, 'salary', salary)), JSON_ARRAY())
// FROM employees GROUP BY department
```

#### JSON path access — MySQL family

```php
use Flowpack\QueryObjectBuilder\MySQL\Q;

// MySQL: the -> and ->> operators
$q = Q::select(Q::n('doc')->jsonExtract(Q::string('$.name')))->from(Q::n('t'));
// SELECT doc -> '$.name' FROM t

// MariaDB: the function form (also works on MySQL)
$q = Q::select(Q\Func::jsonExtract(Q::n('doc'), Q::string('$.name')))->from(Q::n('t'));
// SELECT JSON_EXTRACT(doc, '$.name') FROM t
```

The `->` / `->>` operators validate against `Target::mysql()` only; the
`JSON_EXTRACT` / `JSON_UNQUOTE` function form is portable across both engines.

#### JSON_TABLE — MySQL family

`Q::jsonTable(doc, path)` is a FROM-clause table function; define its columns
with `->columns(closure)`. `->column()` opens a value column and `->path()` gives
its JSON path; `->forOrdinality()` / `->existsPath()` pick the other leaf forms,
the miss handlers (`->defaultOnEmpty()` / `->nullOnError()` / …) attach, and
`->nested()->path()->columns()` recurses.

```php
$q = Q::select(Q::n('jt.id'), Q::n('jt.tag'))
    ->from(Q::n('t'))
    ->from(
        Q::jsonTable(Q::n('t.doc'), '$[*]')->columns(fn ($c) => $c
            ->column('id', 'INT')->path('$.id')
            ->column('ord')->forOrdinality()
            ->nested()->path('$.tags[*]')->columns(fn ($tags) => $tags
                ->column('tag', 'VARCHAR(50)')->path('$'))),
    )->as('jt');
```

```sql
-- MySQL / MariaDB
SELECT jt.id, jt.tag FROM t,
  JSON_TABLE(t.doc, '$[*]' COLUMNS (
    id INT PATH '$.id',
    ord FOR ORDINALITY,
    NESTED PATH '$.tags[*]' COLUMNS (tag VARCHAR(50) PATH '$'))) AS jt
```

### Arrays (PostgreSQL)

Native arrays are a PostgreSQL feature.

```php
use Flowpack\QueryObjectBuilder\PostgreSQL\Q;

$q = Q::select(
    Q\Func::arrayAppend(Q::array(Q::int(1), Q::int(2)), Q::int(3)),
    Q\Func::arrayLength(Q::array(Q::int(1), Q::int(2), Q::int(3)), Q::int(1)),
);
// SELECT array_append(ARRAY[1,2], 3), array_length(ARRAY[1,2,3], 1)

$q = Q::select(Q::n('*'))
    ->from(Q\Func::unnest(Q::array(Q::string('a'), Q::string('b'))))
    ->as('t')->columnAliases('value');
// SELECT * FROM unnest(ARRAY['a','b']) AS t (value)
```

### Subqueries

#### EXISTS and IN

```php
$q = Q::select(Q::n('name'))
    ->from(Q::n('users'))
    ->where(Q::exists(
        Q::select(Q::int(1))
            ->from(Q::n('posts'))
            ->where(Q::n('posts.user_id')->eq(Q::n('users.id'))),
    ));
```

```sql
-- PostgreSQL / MySQL / MariaDB
SELECT name FROM users
WHERE EXISTS (SELECT 1 FROM posts WHERE posts.user_id = users.id)
```

#### IN with bound arguments

```php
$ids = [1, 2, 3];

$q = Q::select(Q::n('username'))
    ->from(Q::n('accounts'))
    ->where(Q::n('id')->in(Q::args(...$ids)));
```

```sql
-- PostgreSQL
SELECT username FROM accounts WHERE id IN ($1, $2, $3)   -- args: [1, 2, 3]
-- MySQL / MariaDB
SELECT username FROM accounts WHERE id IN (?, ?, ?)      -- args: [1, 2, 3]
```

#### ANY / ALL

```php
$q = Q::select(Q::n('id'))->from(Q::n('users'))
    ->where(Q::n('id')->eq(Q::any(
        Q::select(Q::n('user_id'))->from(Q::n('orders')),
    )));
```

```sql
-- PostgreSQL / MySQL / MariaDB
SELECT id FROM users WHERE id = ANY (SELECT user_id FROM orders)
```

### CTEs (WITH)

```php
$q = Q::with('recent_orders')->as(
    Q::select(Q::n('*'))
        ->from(Q::n('orders'))
        ->where(Q::n('created_at')->gt(Q::arg('2023-01-01'))),
)
    ->select(Q::n('customer_name'), Q\Func::count(Q::n('*')))
    ->from(Q::n('recent_orders'))
    ->groupBy(Q::n('customer_name'));
```

```sql
-- PostgreSQL (MySQL/MariaDB render the same, with ? and upper-cased count)
WITH recent_orders AS (
    SELECT * FROM orders WHERE created_at > $1
)
SELECT customer_name, count(*) FROM recent_orders GROUP BY customer_name
```

`Q::withRecursive('t')->columnNames(...)->as(...)` builds recursive CTEs, and
`->appendWith(...)` chains several. On PostgreSQL a `WITH` precedes any
statement; on the MySQL family a leading `WITH` before `UPDATE`/`DELETE` is
MySQL-only (and MariaDB 12.3+):

```php
use Flowpack\QueryObjectBuilder\MySQL\Q;

$q = Q::with('stale')->as(Q::select(Q::n('id'))->from(Q::n('sessions'))->where(Q::n('expired')->eq(Q::int(1))))
    ->deleteFrom(Q::n('users'))->where(Q::n('id')->in(Q::select(Q::n('id'))->from(Q::n('stale'))));
// WITH stale AS (SELECT id FROM sessions WHERE expired = 1) DELETE FROM users WHERE id IN (SELECT id FROM stale)
// ok against Target::mysql() and Target::mariaDb('12.3'); reported against Target::mariaDb('11.4')
```

### INSERT & upsert

The basic INSERT surface is shared: `->columnNames(...)`, `->values(...)`
(repeat for multiple rows), `->setMap([...])`, and `->query(...)` to insert from
a SELECT.

```php
$q = Q::insertInto(Q::n('users'))
    ->columnNames('name', 'email')
    ->values(Q::arg('Jane Doe'), Q::arg('jane@example.com'));
```

```sql
-- PostgreSQL
INSERT INTO users (name, email) VALUES ($1, $2)   -- args: ['Jane Doe', 'jane@example.com']
-- MySQL / MariaDB
INSERT INTO users (name, email) VALUES (?, ?)     -- args: ['Jane Doe', 'jane@example.com']
```

#### Upsert

The engines model conflict handling differently:

```php
// PostgreSQL: INSERT ... ON CONFLICT ... DO UPDATE
use Flowpack\QueryObjectBuilder\PostgreSQL\Q;

$q = Q::insertInto(Q::n('distributors'))
    ->columnNames('did', 'dname')
    ->values(Q::int(5), Q::string('Gizmo Transglobal'))
    ->onConflict(Q::n('did'))->doUpdate()
    ->set('dname', Q::n('EXCLUDED.dname'));
// INSERT INTO distributors (did, dname) VALUES (5, 'Gizmo Transglobal')
// ON CONFLICT (did) DO UPDATE SET dname = EXCLUDED.dname
```

```php
// MySQL / MariaDB: INSERT ... ON DUPLICATE KEY UPDATE
use Flowpack\QueryObjectBuilder\MySQL\Q;

// MySQL: alias the proposed row with AS new, reference it as new.col
$q = Q::insertInto(Q::n('t'))
    ->columnNames('id', 'hits')->values(Q::arg(1), Q::arg(10))->as('new')
    ->onDuplicateKeyUpdate()->set('hits', Q::n('new.hits'));
// INSERT INTO t (id,hits) VALUES (?,?) AS new ON DUPLICATE KEY UPDATE hits = new.hits

// Portable: the VALUES(col) function works on both engines
$q = Q::insertInto(Q::n('t'))
    ->columnNames('id', 'hits')->values(Q::arg(1), Q::arg(10))
    ->onDuplicateKeyUpdate()->set('hits', Q::values('hits'));
// INSERT INTO t (id,hits) VALUES (?,?) ON DUPLICATE KEY UPDATE hits = VALUES(hits)
```

`->as('new')` is MySQL-only (reported against MariaDB). The MySQL family also has
`Q::insertInto(...)->ignore()` (`INSERT IGNORE`) and `Q::replaceInto(...)` (a
`REPLACE` statement with the same surface).

#### RETURNING

```php
// PostgreSQL
use Flowpack\QueryObjectBuilder\PostgreSQL\Q;

$q = Q::insertInto(Q::n('users'))
    ->columnNames('name')->values(Q::arg('Jane'))
    ->returning(Q::n('id'), Q::n('created_at'));
// INSERT INTO users (name) VALUES ($1) RETURNING id, created_at
```

```php
// MariaDB (INSERT / REPLACE / single-table DELETE) — reported against Target::mysql()
use Flowpack\QueryObjectBuilder\MySQL\Q;

$q = Q::insertInto(Q::n('t'))->columnNames('a')->values(Q::arg(1))
    ->returning(Q::n('id'))->as('new_id');
// INSERT INTO t (a) VALUES (?) RETURNING id AS new_id
```

### UPDATE

```php
$q = Q::update(Q::n('films'))
    ->set('kind', Q::arg('Dramatic'))
    ->where(Q::n('kind')->eq(Q::arg('Drama')));
```

```sql
-- PostgreSQL
UPDATE films SET kind = $1 WHERE kind = $2   -- args: ['Dramatic', 'Drama']
-- MySQL / MariaDB
UPDATE films SET kind = ? WHERE kind = ?     -- args: ['Dramatic', 'Drama']
```

Joining another table is spelled per family — PostgreSQL uses `UPDATE ... FROM`,
the MySQL family uses a multi-table `JOIN`:

```php
// PostgreSQL
$q = Q::update(Q::n('employees'))->as('e')
    ->set('department_name', Q::n('d.name'))
    ->from(Q::n('departments'))->as('d')
    ->where(Q::n('e.department_id')->eq(Q::n('d.id')));
// UPDATE employees AS e SET department_name = d.name FROM departments AS d WHERE e.department_id = d.id

// MySQL / MariaDB
$q = Q::update(Q::n('t1'))
    ->leftJoin(Q::n('t2'))->on(Q::n('t1.id')->eq(Q::n('t2.id')))
    ->set('t1.col1', Q::n('t2.col1'))
    ->where(Q::n('t2.col2')->isNull());
// UPDATE t1 LEFT JOIN t2 ON t1.id = t2.id SET t1.col1 = t2.col1 WHERE t2.col2 IS NULL
```

> On the MySQL family, `->orderBy()` / `->limit()` are available on
> *single-table* UPDATE only; combining them with a join raises a
> `QueryBuilderException` when the query is built.

### DELETE

```php
$q = Q::deleteFrom(Q::n('films'))
    ->where(Q::n('kind')->neq(Q::arg('Musical')));
```

```sql
-- PostgreSQL
DELETE FROM films WHERE kind <> $1   -- args: ['Musical']
-- MySQL / MariaDB
DELETE FROM films WHERE kind <> ?    -- args: ['Musical']
```

Joining is `DELETE ... USING` on PostgreSQL and a multi-table `JOIN` on the MySQL
family:

```php
// PostgreSQL
$q = Q::deleteFrom(Q::n('films'))
    ->using(Q::n('producers'))
    ->where(Q::n('producer_id')->eq(Q::n('producers.id')));
// DELETE FROM films USING producers WHERE producer_id = producers.id

// MySQL / MariaDB
$q = Q::deleteFrom(Q::n('t1'))
    ->leftJoin(Q::n('t2'))->on(Q::n('t1.id')->eq(Q::n('t2.id')))
    ->where(Q::n('t2.id')->isNull());
// DELETE t1.* FROM t1 LEFT JOIN t2 ON t1.id = t2.id WHERE t2.id IS NULL
```

### Functions & operators

#### CASE

```php
$q = Q::select(
    Q::n('name'),
    Q::case()
        ->when(Q::n('salary')->lt(Q::int(30000)))->then(Q::string('Low'))
        ->when(Q::n('salary')->lt(Q::int(70000)))->then(Q::string('Medium'))
        ->else(Q::string('High'))
        ->end(),
)->from(Q::n('employees'));
```

```sql
-- PostgreSQL / MySQL / MariaDB
SELECT name,
       CASE WHEN salary < 30000 THEN 'Low'
            WHEN salary < 70000 THEN 'Medium'
            ELSE 'High' END
FROM employees
```

#### Casts

```php
// PostgreSQL: the :: operator via ->cast()
$q = Q::select(Q::n('articles.content')->cast('text'))->from(Q::n('articles'));
// SELECT articles.content::text FROM articles

// MySQL / MariaDB: CAST / CONVERT through the facade
$q = Q::select(Q::cast(Q::n('a'), 'UNSIGNED'), Q::convert(Q::n('a'), 'DECIMAL(10,2)'));
// SELECT CAST(a AS UNSIGNED), CONVERT(a, DECIMAL(10,2))
```

#### Scalar functions

Each family exposes its own curated function set via `Q\Func`:

```php
// PostgreSQL
$q = Q::select(Q\Func::upper(Q::n('name')), Q\Func::extract('year', Q::n('created_at')))
    ->from(Q::n('users'));
// SELECT upper(name), EXTRACT(year FROM created_at) FROM users

// MySQL / MariaDB
$q = Q::select(Q\Func::upper(Q::n('name')), Q\Func::dateAdd(Q::n('created'), Q::interval(Q::int(1), 'DAY')))
    ->from(Q::n('users'));
// SELECT UPPER(name), DATE_ADD(created, INTERVAL 1 DAY) FROM users
```

Anything not on `Q\Func` is reachable through the raw escape hatch
`Q::func('NAME', ...args)`.

### Locking

`->forUpdate()` (optionally `->nowait()` / `->skipLocked()`) is shared. The
shared lock diverges within the MySQL family:

```php
use Flowpack\QueryObjectBuilder\MySQL\Q;

// MySQL: FOR SHARE (+ of() / nowait() / skipLocked())
$q = Q::select(Q::n('id'))->from(Q::n('t'))->forShare()->of('t')->nowait();
// SELECT id FROM t FOR SHARE OF t NOWAIT

// MariaDB: LOCK IN SHARE MODE
$q = Q::select(Q::n('id'))->from(Q::n('t'))->lockInShareMode();
// SELECT id FROM t LOCK IN SHARE MODE
```

`->of(...)` is MySQL-only even on `FOR UPDATE`; validating a query that uses it
against `Target::mariaDb()` reports it.

## Parameters

### Positional parameters

Each `Q::arg()` becomes a placeholder in order of appearance — `$1`, `$2`, … on
PostgreSQL, `?` on the MySQL family:

```php
$q = Q::select(Q::n('*'))
    ->from(Q::n('users'))
    ->where(Q::and(
        Q::n('name')->like(Q::arg('John%')),
        Q::n('active')->eq(Q::arg(true)),
    ));

[$sql, $args] = Q::build($q)->toSql();
// PostgreSQL: SELECT * FROM users WHERE name LIKE $1 AND active = $2   args: ['John%', true]
// MySQL:      SELECT * FROM users WHERE name LIKE ? AND active = ?     args: ['John%', true]
```

### Named parameters

`Q::bind()` declares a named placeholder; bind the values with `withNamedArgs()`:

```php
$q = Q::select(Q::n('*'))
    ->from(Q::n('users'))
    ->where(Q::n('name')->like(Q::bind('search')));

[$sql, $args] = Q::build($q)->withNamedArgs(['search' => 'John%'])->toSql();
```

On PostgreSQL a reused name reuses its `$n` placeholder. On the MySQL family a
`?` placeholder is not reusable, so each occurrence of a name emits its own `?`,
each bound to the same value. Named and positional parameters can be mixed.

## Validation & errors

By default the builder validates while rendering; problems are collected and
thrown together as one `QueryBuilderException` from `toSql()`. There are three
mechanisms:

- **Advisory value checks** — a suspect *value or modifier* in an otherwise
  well-formed statement: an invalid identifier or cast type, an empty `CASE`, a
  `DISTINCT` on an aggregate whose grammar rejects it. These throw when built but
  still render under `Q::build($q)->withoutValidation()->toSql()` — the escape
  hatch for callers who know better.

  ```php
  Q::build(Q::n('foo bar'))->toSql();                       // throws: identifier: invalid: foo bar
  [$sql] = Q::build(Q::n('foo bar'))->withoutValidation()->toSql();  // 'foo bar'
  ```

- **Mutually-exclusive builder state** — two options that cannot coexist in one
  statement (e.g. setting both `values` and a `query` on an INSERT, or
  `ORDER BY`/`LIMIT` on a multi-table UPDATE/DELETE). This is builder-API misuse,
  so it *always* throws, even with validation disabled.

- **Target validation** (MySQL family only, opt-in) —
  `Q::build($q)->withValidateTarget(Target::mysql() | mariaDb($version))` reports
  constructs the target engine/version cannot express. See
  [MySQL & MariaDB](#opt-in-target-validation).

## Executing queries

The builder is driver-agnostic: it produces a SQL string with the dialect's
placeholders and a positional argument list. Feed both to any layer that speaks
that dialect's placeholders.

**PostgreSQL** (e.g. the [`pgsql` extension](https://www.php.net/manual/en/book.pgsql.php)):

```php
use Flowpack\QueryObjectBuilder\PostgreSQL\Q;

$conn = pg_connect('host=localhost dbname=app user=app');

$q = Q::select(Q::n('name'), Q::n('email'))
    ->from(Q::n('users'))
    ->where(Q::n('active')->eq(Q::arg(true)));

[$sql, $args] = Q::build($q)->toSql();

$result = pg_query_params($conn, $sql, $args);
while ($row = pg_fetch_assoc($result)) {
    printf("Name: %s, Email: %s\n", $row['name'], $row['email']);
}
```

**MySQL / MariaDB** (e.g. PDO):

```php
use Flowpack\QueryObjectBuilder\MySQL\Q;

$pdo = new PDO('mysql:host=localhost;dbname=app', 'app', 'secret');

$q = Q::select(Q::n('name'), Q::n('email'))
    ->from(Q::n('users'))
    ->where(Q::n('active')->eq(Q::arg(true)));

[$sql, $args] = Q::build($q)->toSql();

$stmt = $pdo->prepare($sql);
$stmt->execute($args);
foreach ($stmt as $row) {
    printf("Name: %s, Email: %s\n", $row['name'], $row['email']);
}
```

## Best practices

### Reuse expressions and base queries

```php
$userName  = Q::n('users.name');
$userEmail = Q::n('users.email');

$q = Q::select($userName, $userEmail)->from(Q::n('users'));
```

### Build queries conditionally with `applyIf`

Builders expose `applyIf()` so optional clauses read top-to-bottom without
breaking the fluent chain:

```php
$q = Q::update(Q::n('films'))
    ->set('kind', Q::arg('Dramatic'))
    ->where(Q::n('kind')->eq(Q::arg('Drama')))
    ->applyIf($onlyActive, fn ($q) => $q->where(Q::n('archived')->eq(Q::bool(false))));
```

### Organise complex reports with CTEs

Break a large query into named, readable parts with `Q::with()` and chain
several CTEs with `appendWith()`.

## Development

```bash
composer install

composer test       # run the Pest test suite
composer analyse    # run PHPStan (level max)
```

Both must pass for any change.

## License

Licensed under the [GNU General Public License v3.0 or later](LICENSE).
