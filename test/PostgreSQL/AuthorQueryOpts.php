<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\Test\PostgreSQL;

/**
 * Test fixture: options driving how an author is queried.
 *
 * PHP has no inline/local types, so the Go test's local `authorQueryOpts`
 * struct becomes a small readonly value object. Named constructor arguments
 * mimic Go's struct field literals; defaults mimic Go's zero value.
 */
final readonly class AuthorQueryOpts
{
    public function __construct(
        public bool $includeBooks = false,
    ) {
    }
}
