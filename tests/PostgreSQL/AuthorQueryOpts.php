<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\Test\PostgreSQL;

/**
 * Test fixture: options driving how an author is queried.
 */
final readonly class AuthorQueryOpts
{
    public function __construct(
        public bool $includeBooks = false,
    ) {
    }
}
