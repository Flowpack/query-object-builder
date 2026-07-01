<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MariaDB\Builder;

use Flowpack\QueryObjectBuilder\MySQL\Builder\AddsUpsertAssignment;

/**
 * The INSERT builder state inside an `ON DUPLICATE KEY UPDATE` clause, where
 * {@see AddsUpsertAssignment::set()} adds the assignments applied when a unique
 * key already exists.
 */
final class OnDuplicateKeyUpdateInsertBuilder extends InsertBuilder
{
    use AddsUpsertAssignment;
}
