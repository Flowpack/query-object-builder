<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * Builds a window function call such as `row_number()`. Call {@see over()} to
 * turn it into an `OVER (...)` window expression.
 */
final class WindowFuncBuilder
{
    public function __construct(
        private readonly Exp $funcCall,
    ) {
    }

    /**
     * Add the `OVER` clause. Pass an existing window name to reference a window
     * from the query's `WINDOW` clause, or omit it and refine the window inline
     * via {@see WindowFuncCallBuilder::partitionBy()} / {@see WindowFuncCallBuilder::orderBy()}.
     */
    public function over(string $existingWindowName = ''): WindowFuncCallBuilder
    {
        return new WindowFuncCallBuilder($this->funcCall, new WindowDefinition($existingWindowName));
    }
}
