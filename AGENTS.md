# Query Object Builder

A fluent, immutable SQL query builder for PHP 8.4+. The public API is a
per-dialect facade (currently PostgreSQL); the query model and rendering live in
a `Builder` sub-namespace.

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
- `test/PostgreSQL/` — PHPUnit tests and small `readonly` option fixtures.

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

### Public vs internal API

The user-facing surface is the facades (`Q`, `Q\Func`), the fluent builder
methods, the expression objects they return, and `QueryBuilder::toSql()`.

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

### Comments

- No references to the Go port; no "what PHP can't do that Go can" explanations;
  no TODO / "not yet supported" lists.
- Facade and fluent-API methods: short, user-oriented docs — especially gotchas
  ("Multiple calls are joined with AND", "the JSON selection is always the first
  select element").
- Internal implementation / `writeSql`: "why" comments only (e.g. join-vs-comma,
  WHERE-before-GROUP-BY, RECURSIVE-written-once).
- Don't strip purposeful comments; do strip comments that merely restate the code.

### Tests

- Assert generated SQL through the `AssertSql` trait, which ignores insignificant
  whitespace; expected SQL can be written readably in a nowdoc.
- Option/parameter bags from ported tests become small `final readonly` value
  objects under `test/PostgreSQL/` (named constructor args, defaulted fields).

## Verify

```
vendor/bin/phpunit
vendor/bin/phpstan analyse src test --level=max
```

Both must pass for any change.
