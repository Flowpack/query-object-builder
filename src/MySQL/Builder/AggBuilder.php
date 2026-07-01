<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * Builds an aggregate function call such as `COUNT(expr)`, `SUM(DISTINCT expr)`
 * or `AVG(expr)`. Call {@see over()} to use it as a window function.
 */
class AggBuilder extends ExpBase
{
    /**
     * Aggregates whose grammar accepts a leading DISTINCT, per the MySQL 8.4
     * reference (https://dev.mysql.com/doc/refman/8.4/en/aggregate-functions.html).
     * The others built through this class — JSON_ARRAYAGG / JSON_OBJECTAGG, the
     * BIT_AND / BIT_OR / BIT_XOR bit aggregates, the STDDEV_* / VAR_* statistics
     * functions and MEDIAN — reject it. Stored uppercase for case-insensitive lookup.
     *
     * @var array<string, true>
     */
    private const DISTINCT_SUPPORTED = [
        'AVG' => true, 'COUNT' => true, 'MAX' => true, 'MIN' => true, 'SUM' => true,
    ];

    /**
     * @param list<Exp> $exps
     * @param Requirement|null $requires the dialect this aggregate is available on, if it is dialect-specific
     */
    public function __construct(
        protected readonly string $name,
        protected readonly array $exps,
        protected readonly bool $distinct = false,
        protected readonly ?Requirement $requires = null,
    ) {
    }

    public function distinct(): self
    {
        return new self($this->name, $this->exps, true, $this->requires);
    }

    /**
     * Use this aggregate as a window function. Pass an existing window name to
     * reference a window from the query's `WINDOW` clause, or omit it and refine
     * the window inline via {@see WindowFuncCallBuilder::partitionBy()} /
     * {@see WindowFuncCallBuilder::orderBy()}.
     */
    public function over(string $existingWindowName = ''): WindowFuncCallBuilder
    {
        return new WindowFuncCallBuilder($this, new WindowDefinition($existingWindowName));
    }

    public function writeSql(SqlBuilder $sb): void
    {
        if ($this->requires !== null) {
            $sb->requireAnyDialect($this->name, $this->requires);
        }

        if ($this->distinct && $sb->isValidating() && !isset(self::DISTINCT_SUPPORTED[strtoupper($this->name)])) {
            $sb->addError(new QueryBuilderException(sprintf('aggregate: %s does not support DISTINCT', $this->name)));
        }

        $sb->writeString($this->name . '(' . ($this->distinct ? 'DISTINCT ' : ''));
        foreach ($this->exps as $i => $exp) {
            if ($i > 0) {
                $sb->writeString(',');
            }
            $exp->writeSql($sb);
        }
        $sb->writeString(')');
    }
}
