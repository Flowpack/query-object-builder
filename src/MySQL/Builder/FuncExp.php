<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * A function-call expression, e.g. `CONCAT(a, b)`.
 */
final class FuncExp extends ExpBase
{
    /**
     * @param list<Exp> $args
     * @param Requirement|null $requires the dialect this function is available on, if it is dialect-specific
     */
    public function __construct(
        private readonly string $name,
        private readonly array $args,
        private readonly ?Requirement $requires = null,
    ) {
    }

    public function writeSql(SqlBuilder $sb): void
    {
        if ($this->requires !== null) {
            $sb->requireAnyDialect($this->name, $this->requires);
        }

        $sb->writeString($this->name . '(');
        foreach ($this->args as $i => $arg) {
            if ($i > 0) {
                $sb->writeString(',');
            }
            $arg->writeSql($sb);
        }
        $sb->writeString(')');
    }
}
