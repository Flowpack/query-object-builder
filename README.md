


```php
use Flowpack\QueryObjectBuilder\PostgreSQL\Q;
use Flowpack\QueryObjectBuilder\PostgreSQL\Q\Fn;

$bookJSON = Q\Fn::jsonBuildObject()
    ->prop("Title", Q::n("books.title"))
    ->prop("AuthorID", Q::n("books.author_id"))
    ->prop("PublicationYear", Q::n("books.publication_year"))
    ->prop("CreatedAt", Q::n("books.created_at"))
    ->prop("UpdatedAt", Q::n("books.updated_at"))
    ->prop("ID", Q::n("books.book_id"));
    
    /*
$bookJSON = Q\Fn::JsonBuildObject(
    Q\Fn::prop("Title", Q::n("books.title")),
    Q\Fn::prop("AuthorID", Q::n("books.author_id")),
    Q\Fn::prop("PublicationYear", Q::n("books.publication_year")),
    Q\Fn::prop("CreatedAt", Q::n("books.created_at")),
    Q\Fn::prop("UpdatedAt", Q::n("books.updated_at")),
    Q\Fn::prop("ID", Q::n("books.book_id")),
)->withAddedProp()->withoutProp()
    
*/

$q = $q->AppendWith(Q::with("author_books")->As(
Q::select(Q::n("author_id"))->
    Select(
        Q::coalesce(
            Q\Fn::jsonAgg($bookJSON)->OrderBy(Q::n("publication_year")),
            Q::string("[]"),
        ),
    )->As("books")->
    From(Q::n("books"))->
    GroupBy(Q::n("author_id")),
))->
LeftJoin(Q::n("author_books"))->Using("author_id")

```