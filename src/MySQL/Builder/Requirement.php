<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * One way a construct's dialect support can be satisfied: a {@see Dialect} and an
 * optional half-open version window `[gteVersion, ltVersion)`. A null bound is open
 * on that side.
 *
 * A construct may list several requirements (e.g. "MySQL, or MariaDB 12.3+"); it is
 * supported when the validation {@see Target} satisfies any of them.
 *
 * @internal
 */
final class Requirement
{
    public function __construct(
        public readonly Dialect $dialect,
        public readonly ?string $gteVersion = null,
        public readonly ?string $ltVersion = null,
    ) {
    }

    /**
     * Whether the given target is this dialect and — if the target carries a version
     * — falls within the version window. A target with no version is version-agnostic
     * and matches any window (only the dialect must match).
     */
    public function satisfiedBy(Target $target): bool
    {
        if ($target->dialect !== $this->dialect) {
            return false;
        }
        if ($target->version === null) {
            return true;
        }
        if ($this->gteVersion !== null && version_compare($target->version, $this->gteVersion, '<')) {
            return false;
        }
        if ($this->ltVersion !== null && version_compare($target->version, $this->ltVersion, '>=')) {
            return false;
        }

        return true;
    }

    /**
     * A human-readable description for error messages, e.g. `MySQL`, `MariaDB 12.3+`
     * or `MariaDB 10.5–13.0`.
     */
    public function describe(): string
    {
        $s = $this->dialect->label();
        if ($this->gteVersion !== null && $this->ltVersion !== null) {
            return $s . ' ' . $this->gteVersion . '–' . $this->ltVersion;
        }
        if ($this->gteVersion !== null) {
            return $s . ' ' . $this->gteVersion . '+';
        }
        if ($this->ltVersion !== null) {
            return $s . ' < ' . $this->ltVersion;
        }

        return $s;
    }
}
