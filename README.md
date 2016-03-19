# Squirrel

Squirrel is a package for Laravel that automatically caches and retrieves models when querying records using [Eloquent ORM](http://laravel.com/docs/eloquent).  When Squirrel is used, you can expect to see orders of magnitude fewer queries to your database, with 100% confidence you will never be retrieving stale data from Cache.

## License

Squirrel is open-sourced software licensed under the [MIT license](http://opensource.org/licenses/MIT)

## Installation

To get started with Squirrel, add to your `composer.json` file as a dependency:

    composer require davidmpeace/squirrel

### Configuration

After installing the Squirrel library, there may be a couple configuration steps required to ensure the library can effectively cache and retrieve Models appropriately.  Namely, we need to establish how to construct the Model class name, from the queried table name.  

By default, Laravel assumes table names are snake case and plural; And class names are singular, and Pascal Case. Squirrel makes the same assumptions, however, it doesn't know which namespace to use.  So you will need to implement the following configuration to let Squirrel know your model namespace.

```php
<?php
use \Laravel\Cache\SquirrelConfig;

/******* REQUIRED CONFIG ********/

// In simple use cases where all Models are in the same namespace, you can simply set the common namespace
// and it will be used for every class name.
SquirrelConfig::setCommonModelNamespace("App");

/******* OPTIONAL DEPENDING ON YOUR APP ********/

// If you need more control over establishing the namespace or the class name, you may implement your own 
// method to map a table name to a class name.  
// Note: This method is only called once per table, then cached for performance considerations
// The following snippet is the default behavior for Squirrel.
SquirrelConfig::setTableToClassMapper( function($tableName) {
    $namespace = "\\App\\";
    $className = studly_case(str_singular($tableName));
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

class MyAppSuperModel extends Model
{
    use Squirrel;
}
```

That's it!  You will now automatically inherit all the magic of Squirrel.

### Per Model Configuration

Sometimes you'll need custom configuration on a per-model basis.  Implement these methods as required to override the default behavior.

```php
<?php
namespace App;

use Illuminate\Database\Eloquent\Model;
use \Laravel\Cache\Squirrel;

class User extends Model
{
    use Squirrel;
    
    // Implement this method, to establish the unique keys on your table.  Doing this gives Squirrel more power
    // in establishing which queries are cacheable.  Return an array of string column names, or nested arrays for 
    // compound keys.
    // Defaults to just: ['id']
    public static function getUniqueKeys()
    {
        return ['id', ['account_id', 'email'] ];
    }
    
    // Simple method that you can implement to either turn cacheing on or off for this model specifically.
    // Defaults to: true.
    protected static function isModelCacheActive()
    {
        return true; 
    }
    
    // Implement this method, to change the expiration minutes timeout, when cacheing this model.
    // Defaults to: 24 hours
    protected static function cacheExpirationMinutes()
    {
        return (60 * 24); 
    }
}
```
