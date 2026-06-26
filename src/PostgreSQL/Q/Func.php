<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Q;

use Flowpack\QueryObjectBuilder\PostgreSQL\Builder\JsonBuildObjectBuilder;

/**
 * Facade for SQL function expressions, accessed as `Q\Func`.
 *
 * This mirrors the Go `fn` package. (It is named `Func` rather than `Fn`
 * because `Fn` is a reserved keyword in PHP and cannot be a class name.)
 */
final class Func
{
    private function __construct()
    {
    }

    /**
     * Build a `json_build_object(...)` expression.
     */
    public static function jsonBuildObject(): JsonBuildObjectBuilder
    {
        return new JsonBuildObjectBuilder(false);
    }

    /**
     * Build a `jsonb_build_object(...)` expression.
     */
    public static function jsonbBuildObject(): JsonBuildObjectBuilder
    {
        return new JsonBuildObjectBuilder(true);
    }
}
