<?php
namespace Eloquent\Cache;

use stdClass;

/**
 * Helper class to parse, and simplify the data structure, for queries evaluated
 * for Squirrel cache.
 * 
 */
class SquirrelQueryParser
{
    const WHERE_CLAUSE_BOOL_AND        = "and";
    const WHERE_CLAUSE_BOOL_OR         = "or";
    const WHERE_CLAUSE_TYPE_BASIC      = "Basic";
    const WHERE_CLAUSE_TYPE_IN         = "In";
    const WHERE_CLAUSE_TYPE_NULL       = "Null";
    const WHERE_CLAUSE_TYPE_NOT_NULL   = "NotNull";
    const NO_DELETED_COLUMN_CHECK      = "DoNotCheck";
    const WHERE_CLAUSE_OPERATOR_EQUALS = "=";

    private $squirrelQueryBuilder = null;
    private $className            = null;

    /**
     * 
     * 
     * @param SquirrelQueryBuilder $squirrelQueryBuilder [description]
     */
    private final function __construct( SquirrelQueryBuilder $squirrelQueryBuilder )
    {
        $this->squirrelQueryBuilder = $squirrelQueryBuilder;
        $this->className             = $squirrelQueryBuilder->getClassName();
    }

    /**
     * This method will attempt to parse the query
     * @return [type] [description]
     */
    public static function findCachedModelsFromQuery( SquirrelQueryBuilder $squirrelQueryBuilder )
    {
        $queryParser = new SquirrelQueryParser($squirrelQueryBuilder);
        return $queryParser->parseQueryAndFindCachedModels();

    }

    /**
     * [parseAndFindCachedModels description]
     * 
     * @return [type] [description]
     */
    private function parseQueryAndFindCachedModels()
    {
        $squirrelQueryBuilder = $this->squirrelQueryBuilder;

        $className           = $this->className;
        $deletedAtColumnName = $this->getClassDeletedAtColumnName( $className );
        $parsedData          = $this->parseQuery();

        if( !$parsedData ) {
            return false;
        }

        $lookupKeys  = is_array($parsedData->cacheLookup) ? $parsedData->cacheLookup : [$parsedData->cacheLookup];

        // Try and find cached models
        $parsedData->foundModels = [];
        foreach( $lookupKeys as $cacheKey ) {
            if( $data = $className::getDataForCacheKey( $cacheKey ) ) {
                $parsedData->foundModels[ $cacheKey ] = $data;
            }
        }

        return $parsedData;
    }


    /**
     * Helper method to get the name of the deleted at column for this class.
     * 
     * @param  string $className
     * @return string
     */
    private function getClassDeletedAtColumnName( $className )
    {
        if( $className ) {
            if( method_exists($className, 'getDeletedAtColumnName') ) {
                return $className::getDeletedAtColumnName();
            }
        }
    }

    /**
     * [getQueryCacheComponents description]
     * 
     * @return stdClass
     */
    private function parseQuery()
    {
        $className           = $this->className;
        $deletedAtColumnName = $this->getClassDeletedAtColumnName( $className );
        $uniqueKeys          = SquirrelGlobalConfig::getUniqueKeysFromClass( $className );

        // Early validation allows us to fail immediately on obvious unsupported query types
        if( !$className || $this->distinct || $this->limit > 1 || empty($uniqueKeys)  ||
            !empty($this->groups) || !empty($this->havings) || !empty($this->orders)  || !empty($this->offset)  || 
            !empty($this->unions) || !empty($this->joins)   || !empty($this->columns) ||  empty($this->wheres) ) {
            return false;
        }

        $wheres       = $this->getWheres();
        $parsedWheres = $this->parseWheres( $wheres );

        if( !$parsedWheres || !in_array($parsedWheres->columns, $uniqueKeys) ) {
            return false;
        }

        return $parsedWheres;
    }

    /**
     * Get's the where clause in a prepared, organized array to be used elsewhere.  It also does a sleu of error checking
     * as it's iterating to ensure the where clause meets the criteria for cacheability.
     * 
     * @return array|bool
     */
    public static function getWheres()
    {
        $whereColumns = [];
        foreach( $queryBuilderWheres as $index => $where ) {
            if( @$where['boolean'] != self::WHERE_CLAUSE_BOOL_AND ) {
                // We cannot search on unique keys If any of the where clause contains a non-AND boolean
                return false;
            }

            $columnName = str_replace("`", "", @$where['column']);                   // Strip out any backticks
            $columnName = substr($columnName, (strrpos($columnName, ".") ?: -1)+1);  // Grab the column name after the dot, if present

            if( array_key_exists($columnName, $whereColumns) ) {
                // If we have more than one where clause, using the same column name, we can't effectively search the unique key
                return false;
            }

            $value = self::getWhereValue($where, $columnName, $deletedAtColumnName);

            if( $value !== null ) {
                $whereColumns[$columnName] = $value;
            } else {
                return false;
            }
        }

        // Sort the columns by the array key, so that no matter which order they are supplied, they are always returned in the same order
        ksort($whereColumns);

        return $whereColumns;
    }


    public function parseWheres()
    {
        if( empty($wheres) ) {
            return false;
        }

        $whereDeletedAt = (@$wheres[$deletedAtColumnName]) ?: static::NO_DELETED_COLUMN_CHECK;
        unset($wheres[$deletedAtColumnName]);

        $inColumnName = null;
        $inValues     = null;
        foreach( $wheres as $columnName => $where ) {
            if(is_array($where) && count($wheres) > 1) {
                // If there is more than 1 where clause, then we only allow basic equals operations.  
                // Having an "In" statement with an array, does not work with multiple columns, and violates unique key checking
                return false;
            } else if(is_array($where) && count($wheres) == 1) {
                $inColumnName = $columnName;
                $inValues     = $where;
            }
        }

        if( empty($wheres) ) {
            return false;
        }
        
        $compositeColumnNames = SquirrelGlobalConfig::sortedColumnString( array_keys($wheres) );

        $lookupKeys = [];
        if( $inValues ) {
            foreach( $inValues as $value ) {
                $lookupKeys[] = serialize([$inColumnName => $value]);
            }
            $wheres = array_pop($wheres);
        } else {
            $lookupKeys = serialize($wheres);
        }

        $return                     = new stdClass();
        $return->columns            = $compositeColumnNames;
        $return->cacheLookup        = $lookupKeys;
        $return->columnValues       = $wheres;
        $return->deletedColumnCheck = $whereDeletedAt;

        return $return;
    }

    /**
     * [getWhereValue description]
     * @param  array  $where [description]
     * @return [type]        [description]
     */
    private static function getWhereValue( array $where, $columnName, $deletedAtColumnName )
    {
        $value = null;

        switch( @$where['type'] ) {
            case self::WHERE_CLAUSE_TYPE_BASIC:
                // We only allow equals and in statements to search on unique keys
                if( @$where['operator'] == self::WHERE_CLAUSE_OPERATOR_EQUALS ) {
                    $value = @$where['value'];
                }
                break;
            case self::WHERE_CLAUSE_TYPE_IN:
                $value = @$where['values'];
                break;
            case self::WHERE_CLAUSE_TYPE_NULL:
            case self::WHERE_CLAUSE_TYPE_NOT_NULL:
                if( $columnName == $deletedAtColumnName ) {
                    // We only allow Null/NotNull where statements on the deleted_at column
                    $value = @$where['type'];
                }
                break;
        }

        return strval($value);
    }

}