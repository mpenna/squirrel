<?php
namespace Eloquent\Cache;

use Cache;

/**
 * Trait for the Squirrel package.  A Laravel package that automatically caches and retrieves models 
 * when querying records using Eloquent ORM.
 * 
 */
trait Squirrel
{
    /**
     * For cacheing to behave the way we expect, we need to remove the model from cache every time it is
     * saved or deleted.  That way, the next time that data is read, we are reading fresh data from our 
     * data source.
     *
     * @access protected
     * @final
     * @static
     * @return null
     */
    protected final static function bootSquirrel()
    {
        if( static::isCacheing() ) {

            static::saved(function ($model) {
                $model->forget();
            });

            static::deleted(function ($model) {
                $model->forget();
            });
        }
    }

    /**
     * Store the object attributes in cache by it's cache keys.
     *
     * @access public 
     * @return null
     */
    public function remember()
    {
        static::rememberDataForAttributes( $this->getAttributes() );
    }

    /**
     * Remove this object from cache.
     *
     * @access public
     * @return null
     */
    public function forget()
    {
        static::forgetDataForAttributes( $this->getAttributes() );
    }

    /**
     * Returns all cache keys for this model instance.
     *
     * @access public
     * @return array  Returns an array of cache keys
     */
    public function cacheKeys()
    {
        return static::cacheKeysForAttributes( $this->getAttributes() );
    }

    /** 
     * Returns a single cache key as the primary cache key for this model instance.
     *
     * @access public
     * @return string
     */
    public function primaryCacheKey()
    {
        return static::primaryCacheKeyForAttributes( $this->getAttributes() );
    }
    
    /**
     * Returns an array of column names that are unique identifiers for this model in the DB.  By default, it will return
     * only ['id'].  Classes using this trait overwrite this method, and return their own array.  The returned array may contain elements
     * that are string column names, or arrays of string column names for composite unique keys.
     *
     * @access public
     * @static
     * @return  array  Returns an array of unique keys.  Elements may be strings, or arrays of strings for a composite index.
     * 
     */
    public static function getUniqueKeys()
    {
        // Default returns just the 'id' field
        return ['id'];
    }

    /**
     * This method is used by the cacheing storate engine, to specify how we should store the model in the cacheing layer.  Since models
     * may have multiple unique keys, we don't want to store the model multiple times for different unique keys.  So, the cacheing engine
     * actually stores references to the master data, rather than copying the data multiple times.
     * 
     * 
     * @return string|array  By default, returns the first element of the array returned from getUniqueKeys()
     */
    public static function getPrimaryUniqueKey()
    {
        $uniqueKeys = static::getUniqueKeys();
        return array_shift($uniqueKeys);
    }
    

    /**
     * Classes using this trait may use their own logic to determine if classes should use cacheing
     * or not.  
     * 
     * If this method returns FALSE, then no cacheing logic will be used for Models of this Class.
     * If this method returns TRUE, then cacheing will be used for Models of this Class.
     *
     * @access protected
     * @static
     * @return bool Return true to use this trait for this Class, or false to disable.
     */
    protected static function isModelCacheActive()
    {
        // Defaulted the trait to Active
        return true; 
    }

    /**
     * Helper method to quickly determine if cacheing should be used for this class.  It will verify the model
     * cache is active, and the global cache option is active.
     *
     * @access public
     * @final
     * @static
     * @return boolean
     */
    public final static function isCacheing()
    {
        return (static::isModelCacheActive() && SquirrelGlobalConfig::isGlobalCacheActive());
    }

    /**
     * Helper method to get the deleted_at column name for this class.  We are checking to see if the "getDeletedAtColumn" method exists
     * because that signifies the using class is also using the SoftDeletes trait.
     * 
     * @return string
     */
    public static function getDeletedAtColumnName()
    {
        $class = get_called_class();
        if( method_exists($class, 'getDeletedAtColumn') ) {
            // If the 'getDeletedAtColumn' method exists, we assume they are using the SoftDeletes trait as well
            return defined($class.'::DELETED_AT') ? $class::DELETED_AT : "deleted_at";
        }
    }

    /**
     * Models using this trait may extend this method to return a different expiration
     * timeout for each Class.  This value can change depending on the frequency, or volatility of the 
     * data in your Model.  By default, this method expires the data after 24 hours.
     *
     * @access protected
     * @static
     * @return  integer Number of minutes before the cache expires automatically.
     */
    protected static function cacheExpirationMinutes()
    {
        return (60 * 24); 
    }

    /**
     * This method will return an array of cache keys that may be used to store this model in cache.  The $modelAttributes
     * array should contain all the fields required to populate the unique keys returned from getUniqueKeys()
     *
     * 
     * @access public
     * @static
     * @final
     * @param  array $modelAttributes
     * @return array Returns an array of cache keys, keyed off the column names.
     */
    public final static function cacheKeysForAttributes( array $modelAttributes )
    {
        $class      = get_called_class();
        $prefix     = SquirrelGlobalConfig::getCacheKeyPrefix($class);

        $uniqueKeys = static::getUniqueKeys();

        $cacheKeys = [];
        foreach( $uniqueKeys as $key ) {
            // Make every element an array, so we can build an array of key=>values for each column
            if( !is_array($key) ) {
                $key = [$key];
            }
            
            $keyedByColumn = [];
            foreach( $key as $column ) {
                if( !array_key_exists($column, $modelAttributes) ) {
                    continue 2; // If the column doesn't exist in the modelAttributes, we can't return the cache key
                }
                $keyedByColumn[$column] = strval($modelAttributes[$column]);
            }

            ksort($keyedByColumn);
            $columnList             = SquirrelGlobalConfig::sortedColumnString( array_keys($keyedByColumn) );
            $cacheKey               = $prefix . serialize($keyedByColumn);
            $cacheKeys[$columnList] = $cacheKey;
        }

        return $cacheKeys;
    }

    /**
     * This method will return only a single cache key, which will be used to store the primary data for the model.  
     * All other cache keys will be stored as references to this key so that data is not duplicated.
     *
     * @access public
     * @static
     * @final
     * @param  array $modelAttributes
     * @return string  The primary cache key to store this model
     */
    public static function primaryCacheKeyForAttributes( array $modelAttributes )
    {
        $class = get_called_class();

        $cacheKeys        = static::cacheKeysForAttributes( $modelAttributes );
        $primaryUniqueKey = SquirrelGlobalConfig::getPrimaryUniqueKeyFromClass( $class );

        if( array_key_exists($primaryUniqueKey, $cacheKeys) ) {
            return $cacheKeys[$primaryUniqueKey];
        }
    }

    /**
     * This method will store data for a model via all it's various keys.  Only the primary cache key will actually contain the model data,
     * while the other cache keys will contain pointers to where the primary data resides in cache.
     *
     * @access public
     * @static
     * @param  array $modelAttributes
     * @return null
     */
    public static function rememberDataForAttributes( array $modelAttributes )
    {
        if( !static::isCacheing() ) {
            return false;
        }

        $primaryCacheKey    = static::primaryCacheKeyForAttributes( $modelAttributes );
        $referenceCacheKeys = static::cacheKeysForAttributes( $modelAttributes );

        Cache::put($primaryCacheKey, $modelAttributes, static::cacheExpirationMinutes());

        foreach( $referenceCacheKeys as $referenceCacheKey ) {
            if( $referenceCacheKey != $primaryCacheKey ) {
                Cache::put($referenceCacheKey, $primaryCacheKey, static::cacheExpirationMinutes());
            }
        }
    }

    /**
     * This method will retrieve data for a specific cache key.  If the data returned from cache, is a pointer to another cache record, 
     * it will fetch the pointed data instead, and return the data from the other end point.
     *
     * @access public
     * @static
     * @param  string $cacheKey
     * @return array|null
     */
    public static function getDataForCacheKey( $cacheKey )
    {
        if( !static::isCacheing() ) {
            return false;
        }

        $cacheTagPrefix = SquirrelGlobalConfig::getCacheKeyPrefix();
        if( substr($cacheKey, 0, strlen($cacheTagPrefix) ) != $cacheTagPrefix ) {
            $cacheKey = $cacheTagPrefix . $cacheKey;
        }

        if( $data = Cache::get($cacheKey) ) {
            if( is_string($data) && (substr($data, 0, strlen($cacheTagPrefix)) == $cacheTagPrefix) ) {
                // If the data returned from cache, is a reference to another cache key, we return that one instead.
                $data = Cache::get($data);
            }
        }

        return $data;
    }

    /**
     * This method allows for easy disposing of a model from cache.
     *
     * @access public
     * @static
     * @param  array $modelAttributes
     * @return null
     */
    public static function forgetDataForAttributes( array $modelAttributes )
    {
        $cacheKeys = static::cacheKeysForAttributes( $modelAttributes );

        foreach( $cacheKeys as $cacheKey ) {
            Cache::forget($cacheKey);
        }
    }

    /**
     * Dump data for 1 specific cache key.
     *
     * @access public
     * @static
     * @param  string $cacheKey
     * @return null
     */
    public static function forgetDataForCacheKey( $cacheKey )
    {
        Cache::forget( $cacheKey );
    }

    /*****************************************************************************************************************
     *
     *  !IMPORANT!
     *  Method below is required to proxy requests through Eloquent apropriately.
     *  Do not change or override.
     * 
     *****************************************************************************************************************/

    /**
     * Overwrite default functionality, and return our custom SquirrelQueryBuilder.
     *
     * @access protected
     * @return \Illuminate\Database\Query\Builder
     */
    protected function newBaseQueryBuilder()
    {
        $conn = $this->getConnection();
        $grammar = $conn->getQueryGrammar();
        return new SquirrelQueryBuilder($conn, $grammar, $conn->getPostProcessor());
    }
}