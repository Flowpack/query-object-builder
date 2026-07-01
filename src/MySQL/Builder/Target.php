<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * A render/validation target: the {@see Dialect} and an optional engine version.
 * Passed to {@see QueryBuilder::withValidateTarget()} to render for a specific
 * engine and validate that the query only uses features that engine supports.
 */
final class Target
{
    public function __construct(
        public readonly Dialect $dialect,
        public readonly ?string $version = null,
    ) {
    }

    public static function mysql(?string $version = null): self
    {
        return new self(Dialect::Mysql, $version);
    }

    public static function mariaDb(?string $version = null): self
    {
        return new self(Dialect::MariaDb, $version);
    }

    /**
     * A human-readable description for error messages, e.g. `MySQL` or `MariaDB 11.4`.
     */
    public function describe(): string
    {
        return $this->version === null ? $this->dialect->label() : $this->dialect->label() . ' ' . $this->version;
    }
}
