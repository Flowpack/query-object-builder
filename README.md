# Query Object Builder

[![Latest Stable Version](https://img.shields.io/packagist/v/flowpack/query-object-builder.svg)](https://packagist.org/packages/flowpack/query-object-builder)
[![PHP Version Require](https://img.shields.io/packagist/php-v/flowpack/query-object-builder.svg)](https://packagist.org/packages/flowpack/query-object-builder)
[![CI](https://github.com/Flowpack/query-object-builder/actions/workflows/ci.yml/badge.svg)](https://github.com/Flowpack/query-object-builder/actions/workflows/ci.yml)
[![License](https://img.shields.io/packagist/l/flowpack/query-object-builder.svg)](LICENSE)

A fluent, immutable, fully-typed SQL query builder for PHP 8.4+ with extensive
support for PostgreSQL-specific features.

You compose a query from small, type-safe expression objects and render it to a
parameterized SQL string with bound arguments — never by concatenating strings.

## Why Query Object Builder?

- **Pure PostgreSQL focus** — no compromises for lowest-common-denominator
  multi-database support.
- **JSON-first design** — first-class support for `json_build_object` and
  `json_agg` to build hierarchical data directly in the database.
- **Complete feature set** — CTEs, window functions, arrays, grouping sets,
  `ON CONFLICT`, `RETURNING`, set-returning functions, and more.
- **Type-safe** — builder methods only expose what is valid in the current
  context, so invalid queries are hard to express.
- **Immutable** — every builder method returns a new instance, so base queries
  can be shared and specialised without surprises.

## Requirements

- PHP 8.4 or newer

## Installation

```bash
composer require flowpack/query-object-builder
```

## Quick start

```php
use Flowpack\QueryObjectBuilder\PostgreSQL\Q;

$active = true;

$q = Q::select(Q::n('name'), Q::n('email'))
    ->from(Q::n('users'))
    ->where(Q::n('active')->eq(Q::arg($active)))
    ->orderBy(Q::n('name'));

[$sql, $args] = Q::build($q)->toSql();

echo $sql;             // SELECT name,email FROM users WHERE active = $1 ORDER BY name
var_dump($args);       // [true]
```

`Q::build($q)->toSql()` returns a `[$sql, $args]` pair: a SQL string with
PostgreSQL numbered placeholders (`$1`, `$2`, …) and the positional argument
list to bind. See [Executing queries](#executing-queries) for how to run it.

## Core concepts

### The `Q` facade

`Q` is the single entry point for building queries. It exposes the builder
package as a small set of static factory methods so you never reference the
underlying builder types directly:

- **Statements**: `Q::select()`, `Q::insertInto()`, `Q::update()`,
  `Q::deleteFrom()`, `Q::with()`, `Q::withRecursive()`
- **Identifiers**: `Q::n('table.column')` for names/columns
- **Literals**: `Q::string()`, `Q::int()`, `Q::float()`, `Q::bool()`,
  `Q::null()`, `Q::default()`, `Q::array()`, `Q::interval()`
- **Parameters**: `Q::arg()` (positional), `Q::bind()` (named)
- **Composition**: `Q::and()`, `Q::or()`, `Q::not()`, `Q::exists()`,
  `Q::case()`, `Q::coalesce()`, `Q::func()`, `Q::agg()`

### The `Q\Func` facade

SQL functions live on the `Q\Func` facade: `Q\Func::jsonBuildObject()`,
`Q\Func::jsonAgg()`, `Q\Func::count()`, `Q\Func::sum()`, `Q\Func::upper()`,
`Q\Func::rowNumber()`, `Q\Func::unnest()`, and many more. It is named `Func`
(not `Fn`) because `fn` is a reserved keyword in PHP.

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

Expressions returned by `Q::n()`, `Q::arg()`, literals, and functions carry the
SQL operators as fluent methods: `->eq()`, `->neq()`, `->lt()`, `->gt()`,
`->like()`, `->ilike()`, `->in()`, `->isNull()`, `->isNotNull()`, `->plus()`,
`->minus()`, `->mult()`, `->concat()`, `->cast()`, `->op('*', …)`, and more.
Parentheses are added automatically based on operator precedence.

## Examples

> The builder emits compact, single-line SQL. The SQL shown below is formatted
> for readability — it is otherwise exactly what each query renders.

### Basic queries

#### Simple SELECT

```php
$q = Q::select(Q::n('*'))->from(Q::n('users'));
```

```sql
SELECT * FROM users
```

#### SELECT with WHERE

```php
$q = Q::select(Q::n('name'), Q::n('email'))
    ->from(Q::n('users'))
    ->where(Q::n('active')->eq(Q::bool(true)));
```

```sql
SELECT name, email FROM users WHERE active = true
```

#### SELECT with multiple conditions

```php
$q = Q::select(Q::n('*'))
    ->from(Q::n('employees'))
    ->where(Q::and(
        Q::or(
            Q::n('firstname')->ilike(Q::arg('John%')),
            Q::n('lastname')->ilike(Q::arg('John%')),
        ),
        Q::n('active')->eq(Q::bool(true)),
    ));
```

```sql
SELECT * FROM employees
WHERE (firstname ILIKE $1 OR lastname ILIKE $2) AND active = true
```

#### SELECT DISTINCT

```php
$q = Q::select()->distinct()
    ->select(Q::n('department'))
    ->from(Q::n('employees'));
```

```sql
SELECT DISTINCT department FROM employees
```

#### SELECT with ORDER BY, LIMIT and OFFSET

```php
$q = Q::select(Q::n('name'), Q::n('salary'))
    ->from(Q::n('employees'))
    ->orderBy(Q::n('salary'))->desc()->nullsLast()
    ->limit(Q::int(10))
    ->offset(Q::int(20));
```

```sql
SELECT name, salary FROM employees
ORDER BY salary DESC NULLS LAST
LIMIT 10 OFFSET 20
```

### CRUD operations

#### INSERT with VALUES

```php
$q = Q::insertInto(Q::n('users'))
    ->columnNames('name', 'email', 'active')
    ->values(Q::string('John Doe'), Q::string('john@example.com'), Q::bool(true));
```

```sql
INSERT INTO users (name, email, active)
VALUES ('John Doe', 'john@example.com', true)
```

#### INSERT multiple rows

```php
$q = Q::insertInto(Q::n('products'))
    ->columnNames('name', 'price', 'category')
    ->values(Q::string('Laptop'), Q::float(999.99), Q::string('Electronics'))
    ->values(Q::string('Book'), Q::float(19.99), Q::string('Literature'));
```

```sql
INSERT INTO products (name, price, category) VALUES
    ('Laptop', 999.99, 'Electronics'),
    ('Book', 19.99, 'Literature')
```

#### INSERT from a map of values

```php
$q = Q::insertInto(Q::n('films'))
    ->setMap([
        'code' => 'UA502',
        'title' => 'Bananas',
        'did' => 105,
    ]);
```

```sql
INSERT INTO films (code,did,title) VALUES ($1, $2, $3)
-- args: ['UA502', 105, 'Bananas']
```

#### INSERT from a SELECT

```php
$q = Q::insertInto(Q::n('archived_users'))
    ->query(Q::select(Q::n('*'))->from(Q::n('users'))->where(Q::n('active')->eq(Q::bool(false))));
```

```sql
INSERT INTO archived_users SELECT * FROM users WHERE active = false
```

#### INSERT with RETURNING

```php
$q = Q::insertInto(Q::n('users'))
    ->columnNames('name', 'email')
    ->values(Q::string('Jane Doe'), Q::string('jane@example.com'))
    ->returning(Q::n('id'), Q::n('created_at'));
```

```sql
INSERT INTO users (name, email) VALUES ('Jane Doe', 'jane@example.com')
RETURNING id, created_at
```

#### UPSERT (INSERT … ON CONFLICT)

```php
$q = Q::insertInto(Q::n('distributors'))
    ->columnNames('did', 'dname')
    ->values(Q::int(5), Q::string('Gizmo Transglobal'))
    ->onConflict(Q::n('did'))->doUpdate()
    ->set('dname', Q::n('EXCLUDED.dname'));
```

```sql
INSERT INTO distributors (did, dname) VALUES (5, 'Gizmo Transglobal')
ON CONFLICT (did) DO UPDATE SET dname = EXCLUDED.dname
```

#### UPDATE

```php
$q = Q::update(Q::n('films'))
    ->set('kind', Q::string('Dramatic'))
    ->where(Q::n('kind')->eq(Q::string('Drama')));
```

```sql
UPDATE films SET kind = 'Dramatic' WHERE kind = 'Drama'
```

#### UPDATE with FROM

```php
$q = Q::update(Q::n('employees'))->as('e')
    ->set('department_name', Q::n('d.name'))
    ->from(Q::n('departments'))->as('d')
    ->where(Q::n('e.department_id')->eq(Q::n('d.id')));
```

```sql
UPDATE employees AS e SET department_name = d.name
FROM departments AS d
WHERE e.department_id = d.id
```

#### DELETE

```php
$q = Q::deleteFrom(Q::n('films'))
    ->where(Q::n('kind')->neq(Q::string('Musical')));
```

```sql
DELETE FROM films WHERE kind <> 'Musical'
```

#### DELETE with USING

```php
$q = Q::deleteFrom(Q::n('films'))
    ->using(Q::n('producers'))
    ->where(Q::and(
        Q::n('producer_id')->eq(Q::n('producers.id')),
        Q::n('producers.name')->eq(Q::string('foo')),
    ));
```

```sql
DELETE FROM films USING producers
WHERE producer_id = producers.id AND producers.name = 'foo'
```

### Joins

#### INNER JOIN

```php
$q = Q::select(Q::n('u.name'), Q::n('p.title'))
    ->from(Q::n('users'))->as('u')
    ->join(Q::n('posts'))->as('p')->on(Q::n('u.id')->eq(Q::n('p.user_id')));
```

```sql
SELECT u.name, p.title FROM users AS u
JOIN posts AS p ON u.id = p.user_id
```

#### LEFT JOIN

```php
$q = Q::select(Q::n('u.name'), Q::n('p.title'))
    ->from(Q::n('users'))->as('u')
    ->leftJoin(Q::n('posts'))->as('p')->on(Q::n('u.id')->eq(Q::n('p.user_id')));
```

```sql
SELECT u.name, p.title FROM users AS u
LEFT JOIN posts AS p ON u.id = p.user_id
```

#### JOIN with USING

```php
$q = Q::select(Q::n('u.name'), Q::n('p.title'))
    ->from(Q::n('users'))->as('u')
    ->join(Q::n('posts'))->as('p')->using('user_id');
```

```sql
SELECT u.name, p.title FROM users AS u
JOIN posts AS p USING (user_id)
```

### Aggregation & grouping

#### GROUP BY with an aggregate

```php
$q = Q::select(Q::n('department'))
    ->select(Q\Func::count(Q::n('*')))->as('employee_count')
    ->from(Q::n('employees'))
    ->groupBy(Q::n('department'));
```

```sql
SELECT department, count(*) AS employee_count
FROM employees
GROUP BY department
```

#### GROUP BY with HAVING

```php
$q = Q::select(Q::n('department'))
    ->select(Q\Func::avg(Q::n('salary')))->as('avg_salary')
    ->from(Q::n('employees'))
    ->groupBy(Q::n('department'))
    ->having(Q\Func::avg(Q::n('salary'))->gt(Q::int(50000)));
```

```sql
SELECT department, avg(salary) AS avg_salary
FROM employees
GROUP BY department
HAVING avg(salary) > 50000
```

#### GROUP BY ROLLUP

```php
$q = Q::select(Q::n('department'), Q::n('job_title'), Q\Func::sum(Q::n('salary')))
    ->from(Q::n('employees'))
    ->groupBy()
    ->rollup(
        Q::exps(Q::n('department')),
        Q::exps(Q::n('job_title')),
    );
```

```sql
SELECT department, job_title, sum(salary)
FROM employees
GROUP BY ROLLUP (department, job_title)
```

#### GROUP BY GROUPING SETS

```php
$q = Q::select(Q::n('department'), Q::n('job_title'), Q\Func::sum(Q::n('salary')))
    ->from(Q::n('employees'))
    ->groupBy()
    ->groupingSets(
        Q::exps(Q::n('department')),
        Q::exps(Q::n('job_title')),
        Q::exps(),
    );
```

```sql
SELECT department, job_title, sum(salary)
FROM employees
GROUP BY GROUPING SETS (department, job_title, ())
```

### Window functions

#### ROW_NUMBER over a partition

```php
$q = Q::select(
    Q::n('name'),
    Q::n('salary'),
    Q\Func::rowNumber()->over()->partitionBy(Q::n('department'))->orderBy(Q::n('salary'))->desc(),
)->from(Q::n('employees'));
```

```sql
SELECT name, salary,
       row_number() OVER (PARTITION BY department ORDER BY salary DESC)
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
SELECT sum(salary) OVER w, avg(salary) OVER w
FROM empsalary
WINDOW w AS (PARTITION BY depname ORDER BY salary DESC)
```

### JSON operations

#### Build a JSON object

```php
$q = Q::select(
    Q\Func::jsonBuildObject()
        ->prop('id', Q::n('id'))
        ->prop('name', Q::n('name'))
        ->prop('email', Q::n('email')),
)->from(Q::n('users'));
```

```sql
SELECT json_build_object('id', id, 'name', name, 'email', email)
FROM users
```

#### JSON aggregation

```php
$q = Q::select(
    Q::n('department'),
    Q\Func::jsonAgg(
        Q\Func::jsonBuildObject()
            ->prop('name', Q::n('name'))
            ->prop('salary', Q::n('salary')),
    )->orderBy(Q::n('name')),
)
    ->from(Q::n('employees'))
    ->groupBy(Q::n('department'));
```

```sql
SELECT department,
       json_agg(json_build_object('name', name, 'salary', salary) ORDER BY name)
FROM employees
GROUP BY department
```

#### `selectJson` for a JSON-first query

When the query's primary output is a single JSON object, `Q::selectJson()`
makes it the first selection and lets you refine it later with
`applySelectJson()`:

```php
$q = Q::selectJson(
    Q\Func::jsonBuildObject()
        ->prop('Title', Q::n('books.title'))
        ->prop('ID', Q::n('books.book_id')),
)
    ->from(Q::n('books'))
    ->where(Q::n('books.book_id')->eq(Q::arg(2)));
```

```sql
SELECT json_build_object('Title', books.title, 'ID', books.book_id)
FROM books
WHERE books.book_id = $1
```

### Array operations

#### Array construction

```php
$q = Q::select(Q::array(Q::string('a'), Q::string('b'), Q::string('c')));
```

```sql
SELECT ARRAY['a','b','c']
```

#### Array functions

```php
$q = Q::select(
    Q\Func::arrayAppend(Q::array(Q::int(1), Q::int(2)), Q::int(3)),
    Q\Func::arrayLength(Q::array(Q::int(1), Q::int(2), Q::int(3)), Q::int(1)),
);
```

```sql
SELECT array_append(ARRAY[1,2], 3), array_length(ARRAY[1,2,3], 1)
```

#### UNNEST

```php
$q = Q::select(Q::n('*'))
    ->from(Q\Func::unnest(Q::array(Q::string('a'), Q::string('b'), Q::string('c'))))
    ->as('t')->columnAliases('value');
```

```sql
SELECT * FROM unnest(ARRAY['a','b','c']) AS t (value)
```

#### Array aggregation

```php
$q = Q::select(
    Q::n('department'),
    Q\Func::arrayAgg(Q::n('name'))->orderBy(Q::n('name')),
)
    ->from(Q::n('employees'))
    ->groupBy(Q::n('department'));
```

```sql
SELECT department, array_agg(name ORDER BY name)
FROM employees
GROUP BY department
```

### Subqueries

#### EXISTS

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
SELECT name FROM users
WHERE EXISTS (SELECT 1 FROM posts WHERE posts.user_id = users.id)
```

#### IN with a subquery

```php
$q = Q::select(Q::n('name'))
    ->from(Q::n('users'))
    ->where(Q::n('id')->in(
        Q::select(Q::n('user_id'))
            ->from(Q::n('posts'))
            ->where(Q::n('published')->eq(Q::bool(true))),
    ));
```

```sql
SELECT name FROM users
WHERE id IN (SELECT user_id FROM posts WHERE published = true)
```

#### IN with bound arguments

```php
$ids = [1, 2, 3];

$q = Q::select(Q::n('username'))
    ->from(Q::n('accounts'))
    ->where(Q::n('id')->in(Q::args(...$ids)));
```

```sql
SELECT username FROM accounts WHERE id IN ($1, $2, $3)
-- args: [1, 2, 3]
```

#### Correlated subquery

```php
$q = Q::select(Q::n('name'), Q::n('salary'))
    ->from(Q::n('employees'))->as('e1')
    ->where(Q::n('salary')->gt(
        Q::select(Q\Func::avg(Q::n('salary')))
            ->from(Q::n('employees'))->as('e2')
            ->where(Q::n('e1.department')->eq(Q::n('e2.department'))),
    ));
```

```sql
SELECT name, salary FROM employees AS e1
WHERE salary > (
    SELECT avg(salary) FROM employees AS e2
    WHERE e1.department = e2.department
)
```

#### Subquery in FROM

```php
$q = Q::select(Q::n('avg_quantity'))
    ->from(
        Q::select(Q\Func::avg(Q::n('quantity')))->as('avg_quantity')
            ->from(Q::n('sales'))
            ->groupBy(Q::n('brand')),
    )->as('t');
```

```sql
SELECT avg_quantity FROM (
    SELECT avg(quantity) AS avg_quantity FROM sales GROUP BY brand
) AS t
```

### Common Table Expressions (WITH)

#### Simple CTE

```php
$q = Q::with('recent_orders')->as(
    Q::select(Q::n('*'))
        ->from(Q::n('orders'))
        ->where(Q::n('created_at')->gt(Q::string('2023-01-01'))),
)
    ->select(Q::n('customer_name'), Q\Func::count(Q::n('*')))
    ->from(Q::n('recent_orders'))
    ->groupBy(Q::n('customer_name'));
```

```sql
WITH recent_orders AS (
    SELECT * FROM orders WHERE created_at > '2023-01-01'
)
SELECT customer_name, count(*) FROM recent_orders GROUP BY customer_name
```

#### Recursive CTE

```php
$q = Q::withRecursive('employee_recursive')
    ->columnNames('distance', 'employee_name', 'manager_name')->as(
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
```

```sql
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
```

### Functions & operators

#### String functions

```php
$q = Q::select(
    Q\Func::upper(Q::n('name')),
    Q\Func::lower(Q::n('email')),
    Q\Func::initcap(Q::n('title')),
)->from(Q::n('users'));
```

```sql
SELECT upper(name), lower(email), initcap(title)
FROM users
```

#### Date/time functions

```php
$q = Q::select(
    Q\Func::extract('year', Q::n('created_at')),
    Q::n('created_at')->plus(Q::interval('1 day')),
)->from(Q::n('orders'));
```

```sql
SELECT EXTRACT(year FROM created_at), created_at + INTERVAL '1 day'
FROM orders
```

#### Mathematical operators

```php
$q = Q::select(Q::n('price')->op('*', Q::n('quantity')))->as('total')
    ->from(Q::n('order_items'));
```

```sql
SELECT price * quantity AS total FROM order_items
```

#### CASE expressions

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
SELECT name,
       CASE
           WHEN salary < 30000 THEN 'Low'
           WHEN salary < 70000 THEN 'Medium'
           ELSE 'High'
       END
FROM employees
```

#### Casts

```php
$q = Q::select(Q::n('articles.content')->cast('text'))
    ->from(Q::n('articles'))
    ->where(Q::n('articles.content')->cast('text')->ilike(Q::arg('%foo%')));
```

```sql
SELECT articles.content::text FROM articles WHERE articles.content::text ILIKE $1
-- args: ['%foo%']
```

## Parameters

### Positional parameters

Each `Q::arg()` becomes a numbered placeholder in order of appearance:

```php
$q = Q::select(Q::n('*'))
    ->from(Q::n('users'))
    ->where(Q::and(
        Q::n('name')->like(Q::arg('John%')),
        Q::n('active')->eq(Q::arg(true)),
    ));

[$sql, $args] = Q::build($q)->toSql();
```

```sql
SELECT * FROM users WHERE name LIKE $1 AND active = $2
-- args: ['John%', true]
```

### Named parameters

`Q::bind()` declares a named placeholder; bind the values with
`withNamedArgs()`. Reusing the same name reuses its placeholder:

```php
$q = Q::select(Q::n('*'))
    ->from(Q::n('users'))
    ->where(Q::and(
        Q::n('name')->like(Q::bind('search')),
        Q::n('active')->eq(Q::bind('is_active')),
    ));

[$sql, $args] = Q::build($q)
    ->withNamedArgs(['search' => 'John%', 'is_active' => true])
    ->toSql();
```

```sql
SELECT * FROM users WHERE name LIKE $1 AND active = $2
-- args: ['John%', true]
```

Named and positional parameters can be mixed in the same query.

## Executing queries

The builder is driver-agnostic: it produces a SQL string with PostgreSQL
numbered placeholders (`$1`, `$2`, …) and a positional argument list. Feed both
to any layer that speaks PostgreSQL's native placeholders, for example the
[`pgsql` extension](https://www.php.net/manual/en/book.pgsql.php):

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

By default, identifiers are validated while building and an invalid name throws
a `QueryBuilderException`. Skip validation with
`Q::build($q)->withoutValidation()->toSql()` when you trust the input.

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
    ->set('kind', Q::string('Dramatic'))
    ->where(Q::n('kind')->eq(Q::string('Drama')))
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
