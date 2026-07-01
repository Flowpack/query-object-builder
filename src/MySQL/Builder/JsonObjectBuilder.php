<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * Builds a `JSON_OBJECT(...)` expression from a set of key/value properties.
 *
 * Properties keep insertion order; setting an existing key replaces its value
 * while keeping its position. Keys are string literals; for a computed key,
 * build the call through the `Q::func('JSON_OBJECT', ...)` escape hatch.
 */
final class JsonObjectBuilder implements Exp
{
    /**
     * @param array<string, Exp> $props
     */
    public function __construct(
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

        return new self($props);
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

        return new self($props);
    }

    public function writeSql(SqlBuilder $sb): void
    {
        $s = 'JSON_OBJECT(';

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
