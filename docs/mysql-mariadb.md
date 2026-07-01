# MySQL & MariaDB dialects

Alongside the PostgreSQL builder, the package ships two more dialect facades with
the same fluent, immutable, fully-typed design:

- `Flowpack\QueryObjectBuilder\MySQL\Q` — targets **MySQL 8.4 (LTS)**
- `Flowpack\QueryObjectBuilder\MariaDB\Q` — targets **MariaDB 11.x**

Both render the MySQL-family SQL conventions: identifiers are backtick-quoted
(`` `order` ``), parameters are positional `?` placeholders, and string literals
escape both the backslash and the quote. Pick the facade for your engine; the two
expose the same surface except where the engines genuinely differ (see
[Dialect differences](#dialect-differences)).

```php
use Flowpack\QueryObjectBuilder\MySQL\Q;      // or MariaDB\Q

$q = Q::select(Q::n('id'), Q::n('email'))
    ->from(Q::n('orders'))
    ->where(Q::n('id')->eq(Q::arg(1)));

[$sql, $args] = Q::build($q)->toSql();

echo $sql;        // SELECT id,email FROM orders WHERE id = ?
var_dump($args);  // [1]
```

`toSql()` returns a `[$sql, $args]` pair with positional `?` placeholders and the
argument list to bind — feed both to PDO (`$pdo->prepare($sql)->execute($args)`)
or any layer that speaks MySQL/MariaDB placeholders.

> Note: a `?` placeholder is not reusable, so a named `Q::bind('x')` used twice
> emits two `?` and binds its value into each.

## Facades

- **`Q`** — statements (`select`, `insertInto`, `replaceInto`, `update`,
  `deleteFrom`, `with`, `withRecursive`), identifiers (`n`), literals (`string`,
  `int`, `float`, `bool`, `null`, `default`), parameters (`arg`, `bind`),
  composition (`and`, `or`, `not`, `exists`, `any`, `all`, `case`, `coalesce`,
  `nullif`, `greatest`, `least`, `func`, `cast`, `convert`, `interval`), and the
  window frame bounds (`currentRow`, `unboundedPreceding`, `unboundedFollowing`,
  `preceding`, `following`).
- **`Q\Func`** — SQL functions: aggregates (`count`, `sum`, `avg`, `groupConcat`,
  `jsonArrayAgg`, `bitOr`, `stddevPop`, …), string / numeric / date-time / JSON /
  misc scalars, the window functions (`rowNumber`, `rank`, `lag`, `lead`,
  `firstValue`, …), and the special shapes (`GROUP_CONCAT`, `EXTRACT`, `TRIM`).
  It is named `Func` (not `Fn`) because `fn` is a reserved keyword in PHP.

Operators are chainable on the expression objects that `Q::n()`, literals and
functions return: `->eq()`, `->neq()`, `->lt()`, `->like()`, `->regexp()`,
`->nullSafeEq()` (`<=>`), `->in()`, `->isNull()`, `->plus()`, `->jsonExtract()`
(`->`, MySQL), … Things that read as function calls — `CONCAT`, `POW`, `CAST`,
`JSON_CONTAINS` — are built through the facade (`Q::func` / `Q::cast` / `Q\Func`),
not as chained operators.

## Examples

### SELECT, joins, grouping

```php
$q = Q::select(Q::n('country'))
    ->select(Q\Func::count(Q::n('*')))->as('n')
    ->from(Q::n('users'))
    ->groupBy(Q::n('country'))->withRollup()
    ->having(Q::n('country')->isNotNull())
    ->orderBy(Q::n('n'))->desc();
```

```sql
SELECT country,COUNT(*) AS n FROM users
GROUP BY country WITH ROLLUP HAVING country IS NOT NULL ORDER BY n DESC
```

```php
$q = Q::select(Q::n('*'))
    ->from(Q::n('users'))->as('u')
    ->leftJoin(Q::n('orders'))->as('o')->on(Q::n('o.user_id')->eq(Q::n('u.id')));
```

```sql
SELECT * FROM users AS u LEFT JOIN orders AS o ON o.user_id = u.id
```

### Locking

`forUpdate()` is shared; the shared lock is spelled differently per engine:

```php
// MySQL\Q
Q::select(Q::n('id'))->from(Q::n('t'))->forShare()->of('t')->nowait();
// SELECT id FROM t FOR SHARE OF t NOWAIT

// MariaDB\Q
Q::select(Q::n('id'))->from(Q::n('t'))->forShare();
// SELECT id FROM t LOCK IN SHARE MODE
```

### Window functions

```php
$q = Q::select(
    Q::n('depname'),
    Q\Func::rank()->over()->partitionBy(Q::n('depname'))->orderBy(Q::n('salary'))->desc(),
    Q\Func::sum(Q::n('salary'))->over()
        ->partitionBy(Q::n('subject'))->orderBy(Q::n('t'))
        ->rows(Q::unboundedPreceding()),
)->from(Q::n('empsalary'))
    ->window('w')->as()->orderBy(Q::n('salary'));
```

```sql
SELECT depname,
       RANK() OVER (PARTITION BY depname ORDER BY salary DESC),
       SUM(salary) OVER (PARTITION BY subject ORDER BY t ROWS UNBOUNDED PRECEDING)
FROM empsalary WINDOW w AS (ORDER BY salary)
```

### INSERT & upsert

```php
// MySQL: proposed row via the `AS new` alias → Q::inserted('col') is `new.col`
Q::insertInto(Q::n('t'))
    ->columnNames('id', 'hits')->values(Q::arg(1), Q::arg(10))
    ->onDuplicateKeyUpdate()->set('hits', Q::inserted('hits'));
// INSERT INTO t (id,hits) VALUES (?,?) AS new ON DUPLICATE KEY UPDATE hits = new.hits

// MariaDB: proposed row via VALUES(col) → Q::inserted('col') is `VALUES(col)`
Q::insertInto(Q::n('t'))
    ->columnNames('id', 'hits')->values(Q::arg(1), Q::arg(10))
    ->onDuplicateKeyUpdate()->set('hits', Q::inserted('hits'));
// INSERT INTO t (id,hits) VALUES (?,?) ON DUPLICATE KEY UPDATE hits = VALUES(hits)
```

`Q::insertInto(...)->ignore()` renders `INSERT IGNORE`; `Q::replaceInto(...)`
builds a `REPLACE` statement with the same value/column/query surface.

### Multi-table UPDATE / DELETE

```php
Q::update(Q::n('t1'))
    ->leftJoin(Q::n('t2'))->on(Q::n('t1.id')->eq(Q::n('t2.id')))
    ->set('t1.col1', Q::n('t2.col1'))
    ->where(Q::n('t2.col2')->isNull());
// UPDATE t1 LEFT JOIN t2 ON t1.id = t2.id SET t1.col1 = t2.col1 WHERE t2.col2 IS NULL

Q::deleteFrom(Q::n('t1'))
    ->leftJoin(Q::n('t2'))->on(Q::n('t1.id')->eq(Q::n('t2.id')))
    ->where(Q::n('t2.id')->isNull());
// DELETE t1.* FROM t1 LEFT JOIN t2 ON t1.id = t2.id WHERE t2.id IS NULL
```

`ORDER BY` and `LIMIT` are available on single-table UPDATE/DELETE only; using
them with a join raises a `QueryBuilderException` when the query is built.

### RETURNING (MariaDB only)

```php
use Flowpack\QueryObjectBuilder\MariaDB\Q;

Q::insertInto(Q::n('t'))->columnNames('a')->values(Q::arg(1))
    ->returning(Q::n('id'))->as('new_id');
// INSERT INTO t (a) VALUES (?) RETURNING id AS new_id
```

`returning()` is available on MariaDB INSERT, REPLACE and (single-table) DELETE.
It does not exist on the MySQL facade — calling it there is a compile-time error.

## Dialect differences

| Feature | `MySQL\Q` | `MariaDB\Q` |
|---|---|---|
| `LATERAL` from/join (`fromLateral`, `joinLateral`, …) | ✓ | — (not in MariaDB) |
| Shared row lock | `forShare()` → `FOR SHARE` (+ `of()` / `nowait()` / `skipLocked()`) | `forShare()` → `LOCK IN SHARE MODE` |
| `RETURNING` on INSERT / REPLACE / DELETE | — | ✓ (single-table) |
| Leading `WITH` on UPDATE / DELETE | ✓ | — (MariaDB 12.3+; off on the 11.x anchor) |
| Upsert proposed-row ref (`Q::inserted('c')`) | `new.c` (with `AS new`) | `VALUES(c)` |
| JSON path operators `->` / `->>` | ✓ (`->jsonExtract()` / `->jsonExtractText()`) | use `Q\Func::jsonExtract()` / `Q\Func::jsonUnquote()` |
| Dialect-only functions | `regexpLike`, `grouping`, `anyValue`, `jsonPretty`, `jsonSchemaValid*`, `jsonStorage*`, `randomBytes` | `jsonQuery`, `jsonDetailed`, `jsonExists`, `median`, `toChar`, `addMonths`, `monthsBetween`, `chr`, `oct` |

Everything else — the SELECT clause set, joins, `WITH ROLLUP`, `HAVING`,
`ORDER BY`, `LIMIT`/`OFFSET`, `UNION`/`INTERSECT`/`EXCEPT`, CTEs, window functions
and frames, `ON DUPLICATE KEY UPDATE`, multi-table UPDATE/DELETE, and the curated
function set — is identical across the two facades.

## Limitations

The dialects target a curated, query-shaping surface; the following are
deliberately out of scope. Anything omitted remains reachable through the raw
`Q::func(name, ...)` escape hatch.

- **Deferred** (in scope later, behind an explicit method): `PARTITION (...)`
  selection, index hints, `STRAIGHT_JOIN` / `NATURAL JOIN`, the `LIMIT off,count`
  short form, MariaDB `OFFSET..FETCH` and recursive-CTE `CYCLE`, the
  `INSERT/REPLACE ... SET` assignment form, MySQL `VALUES ROW()` / `TABLE`
  sources, the comma-separated multi-table list (the `JOIN` form covers it),
  multi-target `DELETE t1,t2 FROM …`, `UPDATE/DELETE IGNORE`, `MEMBER OF`,
  `JSON_TABLE`, and MariaDB `PERCENTILE_CONT`/`PERCENTILE_DISC` (the `WITHIN
  GROUP` ordered-set shape).
- **Excluded** (not query shape): `INTO OUTFILE/DUMPFILE/@var`, priority /
  optimizer / result hints (`LOW_PRIORITY`, `SQL_CALC_FOUND_ROWS`, …),
  `PROCEDURE`, `ROWS EXAMINED` / `WAIT n`, `FOR PORTION OF`, spatial / GIS,
  encryption / compression, and XML functions.
- **`UPDATE ... RETURNING`** exists on neither engine within the version anchor.

The full per-production ledger lives in
[`mysql-mariadb-design.md` §12](mysql-mariadb-design.md).

## Relationship to the PostgreSQL builder

The MySQL/MariaDB facades mirror the PostgreSQL builder's structure (immutability,
type-state transitions, the `Q` / `Q\Func` split) but model each engine's own SQL
rather than PostgreSQL's. Notably: PostgreSQL operators that MySQL/MariaDB spell as
functions are dropped from the expression surface and reached through `Q\Func`
(`::`→`CAST`/`CONVERT`, `||`→`CONCAT`, `^`→`POW`, `@>`→`JSON_CONTAINS`); PG-only
clauses (`DISTINCT ON`, `FULL JOIN`, `GROUPING SETS`/`CUBE`, `NULLS FIRST/LAST`,
materialized CTEs, `SEARCH`) are absent; and `ON CONFLICT` becomes
`onDuplicateKeyUpdate()`, `RETURNING` becomes MariaDB-only, and PG's
`UPDATE ... FROM` / `DELETE ... USING` become the multi-table `JOIN` forms.
