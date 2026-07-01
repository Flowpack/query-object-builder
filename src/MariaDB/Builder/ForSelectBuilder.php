<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MariaDB\Builder;

use Flowpack\QueryObjectBuilder\MySQL\Builder\SetsLockWaitPolicy;

/**
 * The builder state right after a `FOR UPDATE` lock was started, where
 * {@see SetsLockWaitPolicy::nowait()} / {@see SetsLockWaitPolicy::skipLocked()}
 * set the wait policy. (Locking specific tables with `OF` is not available.)
 */
final class ForSelectBuilder extends SelectBuilder
{
    use SetsLockWaitPolicy;
}
