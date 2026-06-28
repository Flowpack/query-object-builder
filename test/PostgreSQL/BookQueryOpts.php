<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\Test\PostgreSQL;

/**
 * Test fixture: options driving how a book is queried.
 *
 * The Go test's local `bookQueryOpts` struct (which nests `authorQueryOpts`)
 * becomes a readonly value object whose default for the nested options is a
 * fresh {@see AuthorQueryOpts} — the equivalent of Go's zero value.
 */
final readonly class BookQueryOpts
{
    public function __construct(
        public bool $includeGenres = false,
        public bool $includeAuthor = false,
        public AuthorQueryOpts $authorOpts = new AuthorQueryOpts(),
    ) {
    }
}
