<?php
namespace Eloquent\Cache;

use Illuminate\Database\Eloquent\Model;
use Cache;

class SquirrelCache
{
    // global config option that will set the cache to ON or OFF
    private static $cacheActive = true;

    // simple way to namespace cache tags with a unique ID
    private static $cacheKeyPrefix = "Squirrel";

    /**
     * Set's the global cache active setting to true or false.
     * This is the master override switch to turn caching on or off.
     *
     * @param boolean $active
     */
    public static function setCacheActive($active = true)
    {
        static::$cacheActive = (bool)$active;
    }

    /**
     * Returns true if the global cache is currently active.
     *
     * @return boolean
     */
    public static function isCacheActive()
    {
        return (bool)static::$cacheActive;
    }

    /**
     * Set the prefix to be used when storing and retrieving cache records.
     *
     * @param string
     */
    public static function setCacheKeyPrefix($cacheKeyPrefix)
    {
        static::$cacheKeyPrefix = $cacheKeyPrefix;
    }

    /**
     * Returns the cache key prefix, optionally with a class name as well.
     *
     * @param  string $className
     * @return string
     */
    public static function getCacheKeyPrefix($className = null)
    {
        $separator = ":";
        $keyPrefix = (!empty($className)) ? static::$cacheKeyPrefix . $separator . $className : static::$cacheKeyPrefix;
        return $keyPrefix . $separator;
    }

    /**
     * This method will return an array of cache keys that may be used to store
     * this model in cache. The $modelAttributes array should contain all
     * the fields required to populate the unique keys returned from
     * getUniqueKeys().
     *
     * @access public
     * @static
     * @param  array $modelAttributes
     * @return array Returns an array of cache keys, keyed off the column names.
     */
    public static function uniqueKeys(Model $sourceObject)
    {
        $objectKeys      = $sourceObject->getUniqueKeys();
        $primaryKey      = $sourceObject->getKeyName();

        if (!in_array($primaryKey, $objectKeys)) {
            $objectKeys[] = $primaryKey;
        }

        $uniqueKeys = [];
        foreach ($objectKeys as $value) {
            $key = $value;
            if (is_array($key)) {
                sort($key);
                sort($value);
                $key = implode(",", $key);
            }
            $uniqueKeys[$key] = $value;
        }
        ksort($uniqueKeys);
        return $uniqueKeys;
    }

    /**
     * Returns all the cache keys for an object.
     *
     * @param  Model      $sourceObject
     * @param  array|null $modelAttributes
     * @return array
     */
    public static function cacheKeys(Model $sourceObject, array $modelAttributes = null)
    {
        $modelAttributes = (!empty($modelAttributes)) ? $modelAttributes : $sourceObject->getAttributes();
        $uniqueKeys      = static::uniqueKeys($sourceObject);
        $prefix          = static::getCacheKeyPrefix(get_class($sourceObject));

        $cacheKeys = [];
        foreach ($uniqueKeys as $key => $columns) {
            $columns = (!is_array($columns)) ? [$columns] : $columns;

            $keyedByColumn = [];
            foreach ($columns as $column) {
                // If the column doesn't exist in the model attributes, we don't return the cache key at all
                if (!array_key_exists($column, $modelAttributes)) {
                    continue 2;
                }

                $keyedByColumn[$column] = strval($modelAttributes[$column]);
            }

            ksort($keyedByColumn);
            $cacheKeys[$key] = $prefix . serialize($keyedByColumn);
        }

        return $cacheKeys;
    }

    /**
     * [primaryCacheKey description]
     * @param  Model      $sourceObject    [description]
     * @param  array|null $modelAttributes [description]
     * @return [type]                      [description]
     */
    public static function primaryCacheKey(Model $sourceObject, array $modelAttributes = null)
    {
        $keys = static::cacheKeys($sourceObject, $modelAttributes);
        return array_get($keys, $sourceObject->getKeyName());
    }

    /**
     * This method will store data for a model via all its various keys.
     * Only the primary cache key will actually contain the model data,
     * while the other cache keys will contain pointers to where the
     * primary data resides in cache.
     *
     * @access public
     * @static
     * @param  array $modelAttributes
     * @return null
     */
    public static function remember(Model $sourceObject, array $modelAttributes = null)
    {
        $cacheKeys       = static::cacheKeys($sourceObject, $modelAttributes);
        $primaryCacheKey = static::primaryCacheKey($sourceObject, $modelAttributes);
        $expiration      = $sourceObject->cacheExpirationMinutes();

        $modelAttributes = (!empty($modelAttributes)) ? $modelAttributes : $sourceObject->getAttributes();

        Cache::put($primaryCacheKey, $modelAttributes, $expiration);

        foreach ($cacheKeys as $cacheKey) {
            if ($cacheKey != $primaryCacheKey) {
                Cache::put($cacheKey, $primaryCacheKey, $expiration);
            }
        }
    }

    /**
     * This method allows for easy disposing of a model from cache.
     *
     * @access public
     * @static
     * @param  array $modelAttributes
     * @return null
     */
    public static function forget(Model $sourceObject, array $modelAttributes = null)
    {
        $cacheKeys = static::cacheKeys($sourceObject, $modelAttributes);

        foreach ($cacheKeys as $cacheKey) {
            Cache::forget($cacheKey);
        }
    }

    /**
     * This method will retrieve data for a specific cache key. If the data
     * returned from cache is a pointer to another cache record, it will
     * fetch the pointed data instead, and return the data from the
     * other end point.
     *
     * @access public
     * @static
     * @param  string $cacheKey
     * @return array|null
     */
    public static function get($cacheKey)
    {
        if (!static::isCacheActive()) {
            return false;
        }

        $cacheTagPrefix = static::getCacheKeyPrefix();

        if ($data = Cache::get($cacheKey)) {
            if (is_string($data) && (substr($data, 0, strlen($cacheTagPrefix)) == $cacheTagPrefix)) {
                // If the data returned from cache is a reference to another cache key, we return that one instead.
                $data = Cache::get($data);
            }
        }

        return $data;
    }
}
