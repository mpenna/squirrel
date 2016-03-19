# Squirrel

Squirrel is a package for Laravel that automatically caches and retrieves models when querying records using [Eloquent ORM](http://laravel.com/docs/eloquent).  When Squirrel is used, you can expect to see orders of magnitude fewer queries to your database, with 100% confidence you will never be retrieving stale data from Cache.

## License

Squirrel is open-sourced software licensed under the [MIT license](http://opensource.org/licenses/MIT)

## Installation

To get started with Squirrel, add to your `composer.json` file as a dependency:

    composer require davidmpeace/squirrel

### Configuration

After installing the Squirrel library, you simply need to use the Squirrel trait for any model you want to implement cacheing for.  Typically, you would implement the trait in your super-class such that all your sub-classes would automatically inherit the functionality.

```php
<?php
namespace App;

use Illuminate\Database\Eloquent\Model;
use \Laravel\Cache\Squirrel;

class User extends Model
{
    use Squirrel;
    
}
```

the only required configuration is to tell Squirrel how to translate a table name, into a Model Class name in your code base.


\Eloquent\Cache\SquirrelConfig::setCommonModelNamespace( '\Namespace\Path' );
