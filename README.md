# Squirrel

Squirrel is a package for Laravel that automatically caches and retrieves models when querying records using [Eloquent ORM](http://laravel.com/docs/eloquent).  When Squirrel is used, you can expect to see orders of magnitude fewer queries to your database, with 100% confidence you will never be retrieving stale data from Cache.

## License

Squirrel is open-sourced software licensed under the [MIT license](http://opensource.org/licenses/MIT)

## Installation

To get started with Squirrel, add to your `composer.json` file as a dependency:

    composer require davidmpeace/squirrel

### Basic Usage

To use the Squirrel library, you simply need to use the Squirrel trait for any model you want to implement cacheing for.  Typically, you would want to implement the trait in your super-class so that all your sub-classes will automatically inherit the functionality.

```php
<?php
namespace App;

use Illuminate\Database\Eloquent\Model;
use Eloquent\Cache\Squirrel;

class MyAppSuperModel extends Model
{
    use Squirrel;
}
```

That's it!  You will now automatically inherit all the magic of Squirrel.

### Configuration

Sometimes you'll need custom configuration on a per-model basis.  Here are some examples of methods you can implement to override default behavior.

```php
<?php
namespace App;

use Illuminate\Database\Eloquent\Model;
use Eloquent\Cache\Squirrel;

class User extends Model
{
    use Squirrel;
    
    /**
     * Implement this method, to establish additional unique keys on your table.  Doing this gives Squirrel more power
     * in establishing more cacheable queries.  Return an array of string column names, or nested arrays for 
     * compound keys.
     *
     * Default Return: Returns only the primary key for the object.
     */
    public function getUniqueKeys()
    {
        $primaryKey = $this->getKeyName();
        return [$primaryKey, 'uuid', ['account_id', 'email']];
    }
    
    /**
     * Implement this method to cacheing on or off for this model specifically.  Returning false on this method
     * does not affect other models also using Squirrel.
     *
     * Default Return: true
     */
    protected function isCacheActive()
    {
        return true; 
    }
    
    /**
     * Implement this method to change the expiration minutes timeout when cacheing this model.
     *
     * Defaults Return: 24 hours
     */
    public function cacheExpirationMinutes()
    {
        return (60 * 24); 
    }
}
```

### Optional Global Configuration

```php
use Eloquent\Cache\SquirrelCache;

SquirrelCache::setCacheActive(false);              // Turn Squirrel ON or OFF globally
SquirrelCache::isCacheActive();                    // Retrns the config value if Squirrel is active or not globally.
SquirrelCache::setCacheKeyPrefix("Squirrel::");    // Prefix used for all stored Cache Keys
SquirrelCache::getCacheKeyPrefix( "App\User" );    // Returns the cache key prefix, with an option class name
```

### Public Access Methods

These methods are available to any Object using the Squirrel Trait

```php
$user->isCached();                  // Returns true if the current object is stored in cache.
$user->remember();                  // Will store the object in Cache
$user->forget();                    // Will remove the object from Cache
$user->getUniqueKeys();             // Get's all the unique keys on the Object.
$user->cacheKeys();                 // Will return an array of all the Cache keys used to store the object
$user->primaryCacheKey();           // Will return the primary cache key for the object.
$user->cacheExpirationMinutes();    // Returns the number of minutes cache records stay available.
$user->isCacheing();                // Returns true if Cacheing is on for User models
```

### Queries Supported

Squirrel is meant to support multiple unique keys, as well as compound unique keys, so any query that is attempting to bring back a single row based on a unique key will work.  However, you may also perform an "In" query, as long as that's the only part of the query.  See below:

```php
// Simple ID Queries
User::find(1);
User::whereId(1)->get();

// This works because we return a compound unique key on the model
User::whereAccountId(12)->whereEmail('foo@bar.com')->get();  

// Also works, because it will try to find all the individual records
User::whereIn('id', [1,2,3,4,5])->get(); 

// Works because uuid is returned as a unique key on the model
User::whereUuid('12345-12346-123456-12356')->first(); 

// THESE QUERIES DO NOT WORK WITH CACHEING, AND WILL QUERY THE DB

// WON'T CACHE because the "=" equals sign is the only supported operator.
User::where('id', '>', 50)->get();

// WON'T CACHE because the field is not defined as a unique key on the model
User::wherePlanId(23)->first();
```

### Under the Hood

The way Squirrel works is by extending the default `\Illuminate\Database\Query\Builder` Class, which is responsible for executing queries for models.  

By default, Models inherit a method called `newBaseQueryBuilder()` which is responsible for returning the Builder object.  We overload this method so we can return the `SquirrelQueryBuilder` object instead.

The `SquirrelQueryBuilder->get()` method does the actual querying.  However, before we query the data, we first check to see if our model is cached via any unique keys, if so, we return it, otherwise, we do the query.  Finally, after the query is executed, we save the retrieved data in cache so it doesn't get hit again until the data expires.

Cache keys are stored in the following format:

`SquirrelCache::$cacheKeyPrefix . "::" . get_class($model) . "::" . serialize(uniquekey);`
