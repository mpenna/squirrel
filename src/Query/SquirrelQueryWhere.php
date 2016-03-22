<?php
namespace Eloquent\Cache\Query;

use Illuminate\Support\Arr;

/**
 * Helper class to parse `where` array data into a more management data structure vs. the default
 * array with variable array keys.
 * 
 */
class SquirrelQueryWhere
{
    const KEY_TYPE                     = "type";
    const KEY_BOOLEAN                  = "boolean";
    const KEY_COLUMN                   = "column";
    const KEY_OPERATOR                 = "operator";
    const KEY_VALUE                    = "value";
    const KEY_VALUES                   = "values";
    
    const WHERE_CLAUSE_BOOL_AND        = "and";
    const WHERE_CLAUSE_BOOL_OR         = "or";
    
    const WHERE_CLAUSE_TYPE_BASIC      = "Basic";
    const WHERE_CLAUSE_TYPE_IN         = "In";
    const WHERE_CLAUSE_TYPE_NULL       = "Null";
    const WHERE_CLAUSE_TYPE_NOT_NULL   = "NotNull";
    
    const WHERE_CLAUSE_OPERATOR_EQUALS = "=";
    const WHERE_CLAUSE_OPERATOR_IS     = "Is";

    public $boolean     = null;
    public $column      = null;
    public $operator    = null;
    public $value       = null;
    public $type        = null;

    /**
     * Build the object, and hydrate the data.
     * 
     * @param array $whereData
     */
    public function __construct(array $whereData)
    {
        $this->loadData($whereData);
    }

    /**
     * Hydrate the object with appropriate data.
     * 
     * @param  array  $whereData
     * @return null
     */
    private function loadData(array $whereData)
    {
        $this->type        = Arr::get($whereData, self::KEY_TYPE, null);
        $this->boolean     = Arr::get($whereData, self::KEY_BOOLEAN, null);
        $this->column      = $this->getColumnName($whereData);
        $this->operator    = $this->getOperator($whereData);
        $this->value       = $this->getValue($whereData);
    }

    /**
     * Do some processing on the column name, so we get just the column name.  Strip out the table name, and backticks.
     * 
     * @param  array  $whereData
     * @return string
     */
    private function getColumnName(array $whereData)
    {
        $column = Arr::get($whereData, self::KEY_COLUMN, null);
        $column = str_replace("`", "", $column);
        $column = substr($column, (strrpos($column, ".") ?: -1) +1); // Get everything after the `dot`
        return $column;
    }

    /**
     * Get the operator from the where data.  If it's a nullable statement, set to "IS".
     * 
     * @param  array  $whereData
     * @return string
     */
    private function getOperator(array $whereData)
    {
        $operator = Arr::get($whereData, self::KEY_OPERATOR, null);
        if (in_array($this->type, [self::WHERE_CLAUSE_TYPE_NULL, self::WHERE_CLAUSE_TYPE_NOT_NULL])) {
            $operator = self::WHERE_CLAUSE_OPERATOR_IS;
        } elseif ($this->type == self::WHERE_CLAUSE_TYPE_IN) {
            $operator = self::WHERE_CLAUSE_TYPE_IN;
        }
        return $operator;
    }

    /**
     * Standardize the value being checked.  Can be Null/NotNull/String or Array.
     * 
     * @param  array  $whereData
     * @return string|array
     */
    private function getValue(array $whereData)
    {
        $valueKey = self::KEY_VALUE;
        switch ($this->type) {
            case self::WHERE_CLAUSE_TYPE_BASIC:
                $valueKey = self::KEY_VALUE;
                break;
            case self::WHERE_CLAUSE_TYPE_IN:
                $valueKey = self::KEY_VALUES;
                break;
            case self::WHERE_CLAUSE_TYPE_NULL:
            case self::WHERE_CLAUSE_TYPE_NOT_NULL:
                $valueKey = self::KEY_TYPE;
                break;
        }

        $value = Arr::get($whereData, $valueKey, "");
        return (is_array($value)) ? $value : strval($value);
    }
}
