<?php
namespace Eloquent\Cache;

use Illuminate\Support\Str;

/**
 * The global configuration class for Squirrel.
 * 
 */
class SquirrelConfig
{
    // Global config option that will set the cache to ON or OFF
    private static $cacheActive = true;

    // Global config option that defines the common namespace for all your models.
    // Useful only if your models all share the same namespace.
    private static $commonModelNamespace;

    // Simple way to namespace cache tags with a unique ID
    private static $cacheKeyPrefix = "Squirrel::";

    // Callback to handle the conversion of a table name, to a class name
    public static $tableToClassMapper;

    // Internal cache of table name to class name, so we don't have to keep calling the same method everytime
    // to get the same result.
    private static $tableClassNames = [];

    // Local cache of unique key lists, with the array key as the Class Name
    private static $classUniqueKeys = [];

    /**
     * Set's the global cache active setting to true or false.  This is the master override switch to turn 
     * cacheing on or off.
     * 
     * @param boolean $active
     */
    public static function setCacheActive( $active = true ) 
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
     * If all your models are in the same namespace, you can set the common namespace here, and 
     * the default functionality will be able to compile your class names easily.
     * 
     * @param string $namespace
     */
    public static function setCommonModelNamespace( $namespace )
    {
        $namespace = trim($namespace, " \\");
        static::$commonModelNamespace = "\\" . $namespace . "\\";
    }

    /**
     * Helper method to retrieve the common model namespace, if applicable.
     * 
     * @return string
     */
    public static function getCommonModelNamespace()
    {
        return static::$commonModelNamespace;
    }

    /**
     * [setCacheKeyPrefix description]
     * @param [type] $cacheKeyPrefix [description]
     */
    public static function setCacheKeyPrefix( $cacheKeyPrefix )
    {
        static::$cacheKeyPrefix = $cacheKeyPrefix;
    }

    public static function getCacheKeyPrefix( $className = null )
    {
        $keyPrefix = static::$cacheKeyPrefix;
        return ($className) ? $keyPrefix . $className . "::" : $keyPrefix;
    }

    /**
     * Helper method to translate a table name into a unnamespaced class name, using the standard laravel method.
     * 
     * @param  string $tableName
     * @return string
     */
    public static function tableNameToClassName($tableName) {
        return ucfirst(Str::camel(Str::singular($tableName)));
    }

    /**
     * Allows you to set a custom callback method to be used whenever the Cache system needs to map a table name to a Class name.
     * You can override the default functionality any way you'd like.  The default functionality assumes a singular, camel cased, class name
     * prefixed with the common namespace.
     * 
     * @param callable $function
     */
    public static function setTableToClassMapper( callable $function )
    {
        static::$tableToClassMapper = $function;
    }

    /**
     * [callClassMapper description]
     * @param  [type] $tableName [description]
     * @return [type]            [description]
     */
    private static function callClassMapper($tableName)
    {
        if( !static::$tableToClassMapper ) {
            static::$tableToClassMapper = function($tableName) {
                $className = static::tableNameToClassName($tableName);
                $namespace = static::getCommonModelNamespace();
                return $namespace . $className;
            };
        }
        
        $classMapper = static::$tableToClassMapper;

        return $classMapper($tableName);
    }

    /**
     * This method will first see if we have a local cached version of the class name from the table name, and return that, if it 
     * can't find it, it calls the table class mapper function to generate the class name.  This method will not return the class name
     * if the class does not exist.
     * 
     * @param  string $tableName
     * @return string  Fully qualified class name.
     */
    public static function getClassNameFromTableName( $tableName )
    {
        if( !array_key_exists($tableName, static::$tableClassNames) ) {
            // Only call the call-back method to find the class name, if it's not already stored in our local static array
            static::$tableClassNames[ $tableName ] = static::callClassMapper($tableName);
        }

        $className = static::$tableClassNames[ $tableName ];

        if( class_exists($className) ) {
            return $className;
        }
    }

    /**
     * Returns an array of unique keys from the class name specified.
     * 
     * @param  string $className
     * @return array
     */
    public static function getUniqueKeysFromClass( $className ) 
    {
        $uniqueKeys = [];
        if( $className ) {
            if( !array_key_exists($className, static::$classUniqueKeys) ) {
                static::$classUniqueKeys[$className] = static::extractUniqueKeysFromClass($className);
            }

            $uniqueKeys = static::$classUniqueKeys[$className];
        }

        return $uniqueKeys;
    }

    /**
     * Returns the name of the primary unique key from the class name specified.
     * 
     * @param  string $className
     * @return string
     */
    public static function getPrimaryUniqueKeyFromClass( $className )
    {
        $uniqueKeys = static::getUniqueKeysFromClass($className);

        $primaryUniqueKey = null;
        if( method_exists($className, 'getPrimaryUniqueKey') ) {
            $primaryUniqueKey = $className::getPrimaryUniqueKey();
            $primaryUniqueKey = static::sortedColumnString($primaryUniqueKey);
        }
        
        if( !$primaryUniqueKey ) {
            $primaryUniqueKey = array_shift(array_values($uniqueKeys));
        }

        if( $primaryUniqueKey && array_key_exists($primaryUniqueKey, $uniqueKeys) ) {
            return $primaryUniqueKey;
        }
    }

    /**
     * When the unique keys array is not found in the static $classUniqueKeys array, we 
     * go to the class and retrieve the list.  But we only retrieve it once, and save it to the local
     * array cached, so we don't have to re-retrieve it again.
     * 
     * @param  string $className
     * @return array
     */
    private static function extractUniqueKeysFromClass( $className )
    {
        $uniqueKeys = [];
        if( method_exists($className, 'getUniqueKeys') ) {
            $classUniqueKeys = $className::getUniqueKeys();

            foreach( $classUniqueKeys as $uniqueKey ) {
                $hashedAs              = static::sortedColumnString($uniqueKey);
                $uniqueKeys[$hashedAs] = static::sortedColumnsArray($uniqueKey);
            }
        }

        return $uniqueKeys;
    }

    /**
     * Since many methods have returns of either arrays, or strings, this method will sort the value
     * if it's an array, and implode into a string, otherwise, it will just return the data back as it 
     * was sent in.
     * 
     * @param  array|string $columns
     * @return string
     */
    public static function sortedColumnString( $columns )
    {   
        if( is_array($columns) ) {
            sort($columns);
            $columns = implode(",", $columns);
        }
        
        return $columns;
    }

    /**
     * Since many methods have returns of either arrays, or strings, this method will sort the value
     * if it's an array, otherwise, it will just return the data back as it was sent in.
     * 
     * @param  array|string $columns
     * @return array|string
     */
    public static function sortedColumnsArray( $columns )
    {
        if( is_array($columns) ) {
            sort($columns);
        }

        return $columns;
    }
}