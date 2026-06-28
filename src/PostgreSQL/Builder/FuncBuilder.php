<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * A function-call expression that can also stand in a FROM clause (e.g.
 * `generate_series(1, 3)` or `unnest(ARRAY[...]) WITH ORDINALITY`).
 *
 * As a FROM item it may carry `WITH ORDINALITY`, an alias and a column
 * definition list. WITH ORDINALITY together with a column definition list is
 * not valid — use {@see Q::rowsFrom()} instead.
 */
final class FuncBuilder extends ExpBase implements FromLateralExp
{
    /**
     * @param list<Exp> $args
     * @param list<FuncColumnDefinition> $columnDefs
     */
    public function __construct(
        private readonly string $name,
        private readonly array $args,
        private readonly bool $withOrdinality = false,
        private readonly string $alias = '',
        private readonly array $columnDefs = [],
    ) {
    }

    public function withOrdinality(): self
    {
        return new self($this->name, $this->args, true, $this->alias, $this->columnDefs);
    }

    public function as(string $alias): self
    {
        return new self($this->name, $this->args, $this->withOrdinality, $alias, $this->columnDefs);
    }

    /**
     * Add a column definition. Call multiple times to add several.
     */
    public function columnDefinition(string $name, string $type): self
    {
        return new self(
            $this->name,
            $this->args,
            $this->withOrdinality,
            $this->alias,
            [...$this->columnDefs, new FuncColumnDefinition($name, $type)],
        );
    }

    public function writeSql(SqlBuilder $sb): void
    {
        $sb->writeString($this->name . '(');
        foreach ($this->args as $i => $arg) {
            if ($i > 0) {
                $sb->writeString(',');
            }
            $arg->writeSql($sb);
        }

        $s = ')';
        if ($this->withOrdinality) {
            $s .= ' WITH ORDINALITY';
        }
        if ($this->alias !== '') {
            $s .= ' AS ' . $this->alias;
        }

        if ($this->columnDefs !== []) {
            if ($this->withOrdinality) {
                $sb->writeString($s);
                $sb->addError(new QueryBuilderException('func: WITH ORDINALITY is not supported with column definitions, use ROWS FROM instead'));

                return;
            }
            if ($this->alias === '') {
                $s .= ' AS';
            }
            $s .= ' (';
            foreach ($this->columnDefs as $i => $def) {
                if ($i > 0) {
                    $s .= ',';
                }
                $s .= $def->name . ' ' . $def->type;
            }
            $s .= ')';
        }

        $sb->writeString($s);
    }
}
