<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * Builds a MySQL INSERT statement. Adds the `ON DUPLICATE KEY UPDATE` entry point
 * and emits the `AS new` proposed-row alias that makes `Q::inserted('col')`
 * (`new.col`) reachable in the upsert assignments.
 */
class InsertBuilder extends AbstractInsertBuilder
{
    /**
     * Add an `ON DUPLICATE KEY UPDATE` clause. Reference the row that would have
     * been inserted via `Q::inserted('col')` (rendered as `new.col`).
     */
    public function onDuplicateKeyUpdate(): OnDuplicateKeyUpdateInsertBuilder
    {
        return $this->derive(OnDuplicateKeyUpdateInsertBuilder::class);
    }

    /**
     * The `AS new` row alias follows the value rows so the proposed row is reachable
     * as `new.col`. It is not used with the `INSERT ... SELECT` source form.
     */
    protected function writeUpsertRowAlias(SqlBuilder $sb): void
    {
        if ($this->query === null) {
            $sb->writeString(' AS new');
        }
    }
}
