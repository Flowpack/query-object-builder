<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\Test\PostgreSQL;

use Flowpack\QueryObjectBuilder\PostgreSQL\Q;
use Flowpack\QueryObjectBuilder\Test\AssertSql;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SelectBuilderTest extends TestCase
{
    use AssertSql;

    #[Test]
    public function example1(): void
    {
        $q = Q::select(Q::n('f.title'), Q::n('f.did'), Q::n('d.name'), Q::n('f.date_prod'), Q::n('f.kind'))
            ->from(Q::n('distributors'))->as('d')->join(Q::n('films'))->as('f')->using('did');

        $this->assertSqlWriterEquals(
            // language=PostgreSQL
            <<<'SQL'
            SELECT f.title, f.did, d.name, f.date_prod, f.kind
                FROM distributors AS d JOIN films AS f USING (did)
            SQL,
            null,
            $q,
        );
    }
}
