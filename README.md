# Squirrel

Squirrel is a package for Laravel that automatically caches and retrieves models when querying records using [Eloquent ORM](http://laravel.com/docs/eloquent).  When Squirrel is used, you can expect to see orders of magnitude fewer queries to your database, with 100% confidence you will never be retrieving stale data from Cache.

## License

Squirrel is open-sourced software licensed under the [MIT license](http://opensource.org/licenses/MIT)

## Installation

To get started with Squirrel, add to your `composer.json` file as a dependency:

    composer require davidmpeace/squirrel

### Configuration

After installing the Squirrel library, there may be a couple configuration steps required to ensure the library can effectively cache and retrieve Models appropriately.  Namely, we need to establish how to construct the Model class name, from the queried table name.  By default, Laravel assumes table names are snake case and plural; And class names are singular, and Pascal Case; Squirrel assumes the same, however, doesn't know which namespace to use.

You will need to implement the following configuration to let Squirrel know your model namespace.

```php
use \Laravel\Cache\SquirrelConfig;

// In simple use cases where all Models are in the same namespace, you can simply set the common namespace
// and it will be used for every class name.
SquirrelConfig::setCommonModelNamespace('\App\');

// If you need more control over establishing namespace, you may implement your own method to map table name to class name
// The following snippet is the default behavior for Squirrel.
SquirrelConfig::setTableToClassMapper( function($tableName) {
    $namespace = SquirrelConfig::getCommonModelNamespace();
    $className = SquirrelConfig::tableNameToClassName($tableName);
    return $namespace . $className;
});
```

### Basic Usage

To use the Squirrel library, you simply need to use the Squirrel trait for any model you want to implement cacheing for.  Typically, you would implement the trait in your super-class such that all your sub-classes would automatically inherit the functionality.

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
