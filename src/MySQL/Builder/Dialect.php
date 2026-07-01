<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * The engine a query is rendered for. MySQL and MariaDB share one builder; the
 * dialect selects the render-time spelling of the diverging clauses and, when a
 * validation target is set, gates the features one engine cannot express.
 */
enum Dialect: string
{
    case Mysql = 'mysql';
    case MariaDb = 'mariadb';

    /**
     * A human-readable name for error messages.
     */
    public function label(): string
    {
        return match ($this) {
            self::Mysql => 'MySQL',
            self::MariaDb => 'MariaDB',
        };
    }
}
