<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * Builds a `json_build_object(...)` (or `jsonb_build_object(...)`) expression
 * from a set of key/value properties.
 *
 * Immutable: every method returns a new builder. Properties keep insertion
 * order, and setting an existing key updates its value in place (keeping its
 * position) — PHP associative arrays give us exactly the ordered-map semantics
 * of the Go `immutableSliceMap`.
 *
 * Port of the Go `builder.JsonBuildObjectBuilder`.
 */
final class JsonBuildObjectBuilder implements Exp
{
    /**
     * @param array<string, Exp> $props
     */
    public function __construct(
        private readonly bool $isJsonB = false,
        private readonly array $props = [],
    ) {
    }

    /**
     * Set a property; if the key already exists its value is replaced in place.
     */
    public function prop(string $key, Exp $value): self
    {
        $props = $this->props;
        $props[$key] = $value;

        return new self($this->isJsonB, $props);
    }

    /**
     * Set a property only if the condition is true.
     */
    public function propIf(bool $condition, string $key, Exp $value): self
    {
        return $condition ? $this->prop($key, $value) : $this;
    }

    /**
     * Apply the given function to this builder only if the condition is true.
     *
     * @param callable(self): self $apply
     */
    public function applyIf(bool $condition, callable $apply): self
    {
        return $condition ? $apply($this) : $this;
    }

    /**
     * Remove a property by key.
     */
    public function unset(string $key): self
    {
        $props = $this->props;
        unset($props[$key]);

        return new self($this->isJsonB, $props);
    }

    public function writeSql(SqlBuilder $sb): void
    {
        $s = $this->isJsonB ? 'jsonb_build_object(' : 'json_build_object(';

        $i = 0;
        foreach ($this->props as $key => $value) {
            if ($i > 0) {
                $s .= ',';
            }
            $s .= Literals::quoteLiteral($key) . ',';
            $sb->writeString($s);
            $s = '';

            $value->writeSql($sb);
            $i++;
        }

        $sb->writeString($s . ')');
    }
}
