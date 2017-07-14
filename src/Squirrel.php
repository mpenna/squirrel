<?php
namespace Eloquent\Cache;

use Eloquent\Cache\Query\SquirrelQueryBuilder;
use Eloquent\Cache\Exceptions\InvalidSquirrelModelException;

/**
 * Trait for the Squirrel package. A Laravel package that automatically caches
 * and retrieves models when querying records using Eloquent ORM.
 */
trait Squirrel
{
    public function __construct(array $attributes = [])
    {
        if (!is_subclass_of($this, "Illuminate\Database\Eloquent\Model")) {
            throw new InvalidSquirrelModelException("Models using the Squirrel trait must also extend from the base Eloquent Model.");
        }

        return parent::__construct($attributes);
    }

    /**
     * For caching to behave the way we expect, we need to remove the model
     * from the cache every time it is either saved or deleted. That way,
     * the next time that data is read, fresh data will be read from
     * the data source.
     *
     * @access protected
     * @static
     * @return null
     */
    final protected static function bootSquirrel()
    {
        static::saved(function ($model) {
            $model->forget();
        });

        static::deleted(function ($model) {
            $model->forget();
        });
    }

    /**
     * Classes using this trait may use their own logic to determine if classes
     * should use caching or not.
     *
     * If this method returns FALSE, then no caching logic will be used for
     * Models of this Class.
     *
     * If this method returns TRUE, then caching will be used for Models
     * of this Class.
     *
     * @access protected
     * @static
     * @return bool Return true to use this trait for this Class, or false to disable.
     */
    protected function isCacheActive()
    {
        // Defaulted the trait to Active
        return true;
    }

    /**
     * Returns an array of column names that are unique identifiers for this
     * model in the DB. By default, it will return only ['id']. Classes
     * using this trait should overwrite this method, and return their
     * own array. The returned array may contain elements that are
     * string column names, or arrays of string column names for
     * composite unique keys.
     *
     * @access public
     * @static
     * @return  array  Returns an array of unique keys.  Elements may be strings, or arrays of strings for a composite index.
     *
     */
    public function getUniqueKeys()
    {
        $primaryKey = $this->getKeyName();
        return [$primaryKey];
    }

    /**
     * Models using this trait may extend this method to return a different
     * expiration timeout for each class. This value can change depending
     * on the frequency, or volatility of the data in your Model.
     * By default, this method expires the data after 24 hours.
     *
     * @access protected
     * @static
     * @return  integer Number of minutes before the cache expires automatically.
     */
    public function cacheExpirationMinutes()
    {
        return (60 * 24);
    }

    /**
     * Helper method to quickly determine if caching should be used for this
     * class. It will verify the model cache is active, and that the global
     * cache option is active.
     *
     * @access public
     * @final
     * @static
     * @return boolean
     */
    final public function isCacheing()
    {
        return ($this->isCacheActive() && SquirrelCache::isCacheActive());
    }

    /**
     * Store the object attributes in cache by it's cache keys.
     *
     * @access public
     * @return null
     */
    final public function remember()
    {
        SquirrelCache::remember($this, $this->getAttributes());
    }

    /**
     * Remove this object from cache.
     *
     * @access public
     * @return null
     */
    final public function forget()
    {
        SquirrelCache::forget($this, $this->getAttributes());
    }

    /**
     * Returns all cache keys for this model instance.
     *
     * @access public
     * @return array  Returns an array of cache keys
     */
    final public function cacheKeys()
    {
        return SquirrelCache::cacheKeys($this, $this->getAttributes());
    }

    /**
     * Returns just the primary cache key.
     *
     * @return [type] [description]
     */
    final public function primaryCacheKey()
    {
        return SquirrelCache::primaryCacheKey($this, $this->getAttributes());
    }

    /*
    ***************************************************************************
    *
    * !IMPORANT!
    *
    * METHOD BELOW IS REQUIRED TO PROXY REQUESTS THROUGH ELOQUENT
    * DO NOT CHANGE OR OVERRIDE
    *
    ***************************************************************************
    */

    /**
     * Override default functionality and return our custom SquirrelQueryBuilder.
     *
     * @access protected
     * @return \Illuminate\Database\Query\Builder
     */
    protected function newBaseQueryBuilder()
    {
        $conn         = $this->getConnection();
        $grammar      = $conn->getQueryGrammar();
        $queryBuilder = new SquirrelQueryBuilder($conn, $grammar, $conn->getPostProcessor());

        $queryBuilder->setSourceModel($this);

        return $queryBuilder;
    }
}
