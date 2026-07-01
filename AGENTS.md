# Query Object Builder

A fluent, immutable SQL query builder for PHP 8.4+. The public API is a
per-family facade — `PostgreSQL\Q` and `MySQL\Q` (the MySQL family covers both
MySQL and MariaDB in one builder); the query model and rendering live in each
family's `Builder` sub-namespace.

The MySQL family is a single builder for both engines: rendering is fully
determined by construction (never a dialect flag), engine-divergent constructs are
built their own way, and each such construct calls `$sb->requireDialect(...)` /
`requireAnyDialect(...)` while rendering so an opt-in
`Q::build($q)->withValidateTarget(Target::mysql()|mariaDb())` pass reports the
constructs the target cannot express. `Target` carries an optional version for
version-gated features. See `docs/mysql-mariadb.md` and the
`docs/mysql-mariadb-differences.md` catalogue.

The design adapts the Go package `github.com/networkteam/qrb`. We port its
patterns, but **never mention Go in the PHP code or comments** (see *Comments*).

## Layout

- `src/PostgreSQL/Q.php` — the facade: `Q::select()`, `Q::n()`, `Q::with()`,
  `Q::arg()`, `Q::coalesce()`, `Q::string()`, `Q::int()`, `Q::build()`, …
- `src/PostgreSQL/Q/Func.php` — the function facade `Q\Func`
  (`Q\Func::jsonBuildObject()`, `Q\Func::jsonAgg()`, …). It is named `Func`, not
  `Fn`, because `Fn` is a reserved keyword in PHP and cannot be a class name.
- `src/PostgreSQL/Builder/` — expressions, the select-builder family, and the
  internal value objects.
- `tests/` — Pest tests, the `tests/Pest.php` bootstrap, and small `readonly`
  option fixtures.

Each family has its own top-level namespace (`src/PostgreSQL/`, `src/MySQL/`)
mirroring this layout; the paths above show the PostgreSQL one. The two do not
share types — a query built with one is rendered by its own `QueryBuilder`.

## Conventions

### Immutability — the core invariant

Everything in the query model is immutable.

- **State holders** (`SelectQueryParts`, `FromItem`, `Join`, `OutputExpr`,
  `OrderByClause`, `GroupingElement`, `WithQueryItem`) are `final` classes with
  `public readonly` properties and **nothing but a constructor** (plus
  `writeSql()` where they render). No setters, no `with*()` methods, no `clone`.
  They are marked `@internal`.
- **Builders** (`SelectBuilder` and its type-state subclasses, `WithBuilder`, the
  expression builders) return a **new instance** from every method and never
  mutate `$this`.
- **Derivation lives inside the builder package, never on the value objects.**
  A new `SelectQueryParts`/builder is assembled in exactly one place — the
  `protected` `SelectBuilder::derive()` (a `null` argument means "keep
  current"). "Modify the last element" operations each live in a single private
  helper on the relevant subbuilder (e.g. `JoinSelectBuilder::rebuildLastJoin()`),
  so a value object's field list is reconstructed in exactly one spot.
- Do **not** add public `with*()`/mutation methods to the state holders. PHP has
  no package-private visibility, so their constructors are unavoidably `public`;
  `@internal` marks the boundary.

The one deliberate exception is **`SqlBuilder`**, which is mutable: it is the
rendering accumulator, created inside `QueryBuilder::toSql()`, never exposed and
never part of the query model.

`derive()` builds a fresh object instead of cloning — a few hundred nanoseconds
per builder step, negligible next to query execution. Don't trade the
immutability guarantee for build-time micro-optimization.

### Public vs internal API

The user-facing surface is the facades (`Q`, `Q\Func`), the fluent builder
methods, the expression objects they return, and `QueryBuilder::toSql()`.

**`Q\Func` is the *expression* function facade: every method returns an `Exp`**
(directly, or a builder that is an `Exp`) — something usable anywhere an
expression is valid (SELECT list, `WHERE`, `ON`, an argument, …). A construct that
is *not* a general expression — a statement, a clause, or a FROM-only producer
like `JSON_TABLE` (its builder is a `FromExp`, not an `Exp`) — belongs on the `Q`
facade, next to `select`/`from`/`with` and the other constructs, **not** on
`Q\Func`. (This is why `Q::jsonTable()` and PG's `Q::rowsFrom()` live on `Q`.)

The **rendering contract is internal**: `SqlWriter` / `InnerSqlWriter`, every
`writeSql()` method, and `SqlBuilder` are plumbing — users never implement or
call them. Mark them, and the value-object state holders, `@internal`.

### Type-state builders

Builder methods return a more specific builder type so context-dependent methods
are only reachable — and only act on the relevant element — where they make
sense: `from()` → `FromSelectBuilder` (`as()` aliases the from item), `join()` →
`JoinSelectBuilder` (`as()`/`on()`/`using()` act on the join), `orderBy()` →
`OrderBySelectBuilder` (`desc()`/`nullsLast()`), and so on. The transition is
performed by `derive(TargetBuilder::class, …)`.

### writeSql string batching

In `writeSql()`, accumulate literal SQL into a local string and only call
`$sb->writeString()` right before a nested writer must emit (`$child->writeSql($sb)`)
or at the very end. The cost on PHP hot paths is call overhead, not
concatenation, so fewer `writeString()` calls is the win.
`SelectBuilder::writeSelectParts()` is the reference.

### Validation errors

Errors found while rendering are collected on `SqlBuilder` (`$sb->addError(...)`)
and thrown together as one `QueryBuilderException` by `QueryBuilder::toSql()` — a
`writeSql()`/`innerWriteSql()` never throws directly. Which of the two `addError`
shapes to use is decided by a single question: **is there still a well-formed
statement to emit?**

- **Advisory value validation — gate on `$sb->isValidating()`, then keep
  rendering.** The statement *shape* is well-formed but one *value or modifier* is
  suspect: an invalid identifier (`IdentExp`), an unknown type (`TypeExp`), an
  empty `CASE` (`CaseExp`), `DISTINCT` on an aggregate whose grammar rejects it
  (`AggBuilder`). Add the error but **do not `return`** — emit the text anyway, so
  the SQL is fully determined and `Q::build($q)->withoutValidation()` is the escape
  hatch that lets a caller who knows better ship it (the server is the final
  judge). These are the only checks `withoutValidation()` suppresses.
- **Mutually-exclusive builder state — always `addError` (never gated) and
  `return`.** Two builder options cannot coexist in one statement, so there is no
  shape to render: `LATERAL` + `ONLY` (`FromItem`), `values` + `query`
  (`InsertBuilder` / `ReplaceBuilder`), an `ON CONFLICT` constraint name +
  targets, `WITH ORDINALITY` + a column-definition list (`FuncBuilder`),
  multi-table `DELETE`/`UPDATE` + a single-target `ORDER BY`/`LIMIT`. This is
  builder-API misuse, not an invalid value, so `withoutValidation()` must not mask
  it — it always throws.

Target/dialect gating is a third, separate mechanism: `$sb->requireDialect(...)` /
`requireAnyDialect(...)` report a construct the validated target cannot express.
It is opt-in via `Q::build($q)->withValidateTarget(...)` and keys off the target,
not `isValidating()`.

### Dialect-native design

Each dialect's facade and builders model *that dialect's own* SQL; a dialect is
never built as a diff against another.

- **Natural look.** The fluent API mirrors how the SQL reads. What the dialect
  spells as an **operator** (comparisons, arithmetic, `LIKE`, `IS NULL`, plus
  dialect-specific ones like PG `::`/`||` or MySQL `<=>`/`->`) is a chainable
  method on the expression base; what reads as a **function** (`CONCAT`, `POW`,
  `CAST`, `JSON_CONTAINS`, …) is constructed through the facade (`Q::func` /
  `Q::cast` / `Q\Func`), not an operator-style chained method that merely emits a
  function. Don't copy another dialect's expression surface — model the operator
  set the dialect actually has.
- **No dialect is the baseline.** When carrying a pattern across dialects, keep
  the structure (immutability, type-state, `derive()`, `writeSql`) but re-derive
  the API and SQL from the target dialect's grammar — drop what it lacks, add what
  it has.

### Comments

- No references to the Go port; no "what PHP can't do that Go can" explanations;
  no TODO / "not yet supported" lists.
- No cross-*family* framing: never describe the code relative to the other family
  ("PostgreSQL has X", "no FULL JOIN here", "unlike PG") — same spirit as the no-Go
  rule above. **Within** the MySQL family this does not apply to engine
  divergences: a construct that only one engine accepts should say so plainly
  (e.g. "the OF table list is a MySQL extension", "RETURNING is MariaDB-only"),
  since that is exactly the `requireDialect(...)` validation contract.
- Facade and fluent-API methods: short, user-oriented docs — especially gotchas
  ("Multiple calls are joined with AND", "the JSON selection is always the first
  select element").
- Internal implementation / `writeSql`: "why" comments only (e.g. join-vs-comma,
  WHERE-before-GROUP-BY, RECURSIVE-written-once).
- Don't strip purposeful comments; do strip comments that merely restate the code.

### Testing (Pest)

Tests use **Pest 4** (on PHPUnit 12) so the nested structure of qrb's `t.Run`
groups maps directly onto nested `describe()` / `it()`.

- **Structure**: mirror qrb's nesting with `describe()`/`it()`. Helper closures
  shared by a group are defined in the `describe()` body and pulled into each
  `it()` via `use (...)`.
- **Assertion**: a custom expectation, `expect($query)->toRenderSql($sql, $args)`,
  defined in `tests/Pest.php`. It builds the query and compares against `$sql`
  ignoring insignificant whitespace (`normalizeSql()`), so expected SQL can be
  written readably in a nowdoc; pass `null` for `$args` when none are bound.
- **Option bags** from ported tests are small `final readonly` value objects
  under `tests/` (named constructor args, defaulted fields).
- **Static analysis**: `imsuperlative/phpstan-pest` teaches PHPStan about Pest
  (including resolving custom `expect()->extend()` expectations). One gotcha: the
  value inside an `extend()` closure is statically untyped, so narrow it through a
  tiny typed helper (`asSqlWriter()`) rather than an `instanceof` in the closure —
  an `instanceof` there reads as "always false".

### Porting from qrb

The query API is being ported from `qrb` incrementally; new SELECT clauses,
expressions, functions and the INSERT/UPDATE/DELETE builders follow the patterns
above. When translating its tests:

- Go subtests (`t.Run`) → nested `describe()`/`it()`.
- Go local helper funcs → arrow-function closures (`static fn (...) => ...`),
  which capture earlier closures by value.
- Copy the expected SQL verbatim into a nowdoc — `toRenderSql` handles whitespace.

## Verify

```
vendor/bin/pest
vendor/bin/phpstan analyse
```

Both must pass for any change. (PHPStan config — `level: max`, paths, and the
Pest extension — lives in `phpstan.neon`.)
