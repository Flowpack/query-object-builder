<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * The kind of a {@see JsonTableColumn}: a value column (`type PATH ...`), an
 * existence flag (`type EXISTS PATH ...`), the row counter (`FOR ORDINALITY`), or
 * a nested column list (`NESTED PATH ... COLUMNS (...)`).
 *
 * @internal
 */
enum JsonTableColumnKind
{
    case Path;
    case Exists;
    case Ordinality;
    case Nested;
}
