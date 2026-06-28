<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\Test\PostgreSQL;

/**
 * Test fixture: options driving how a book is queried.
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
