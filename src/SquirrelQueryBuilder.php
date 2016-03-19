<?php
namespace Eloquent\Cache;

/**
 * This class is used as a proxy to the back-end Eloquent ORM.  It allows
 * us to first check if we have cache moddels, before we actually do any queries 
 * to the database.
 * 
 */
class SquirrelQueryBuilder extends \Illuminate\Database\Query\Builder
{
    /**
     * Helper method to get the class name based on the table name being used.
     *
     * @access public
     * @return string
     */
    public function getClassName()
    {
        return SquirrelConfig::getClassNameFromTableName( $this->from );
    }

    /**
     * Quick check to ensure cacheing is active, before we do any more complex query checks.
     *
     * @access public
     * @return boolean
     */
    public function isClassCacheing()
    {
        if( $className = $this->getClassName() ) {
            if( method_exists($className, 'isCacheing') ) {
                return $className::isCacheing();
            }
        }

        return false;
    }

    /**
     * This method is overloaded from the base Elquent builder, so we can first see if the cached models already exist, and we can then bypass checking the database
     * entirely.
     *
     * @access public
     * @param  array  $columns
     * @return array
     */
    public function get($columns = array('*'))
    {
        $cachedModels = null;

        // Verify we are not passing explicit columns.  If columns are passed, then cacheing methods should not be attempted.
        if( empty($columns) || (count($columns) == 1 && $columns[0] == "*") ) {
            // If the class we are querying has cacheing enabled, then we see if we can find any cached models first, before we execute the parent query.
            if( $this->isClassCacheing() ) {
                $cachedModels = SquirrelQueryParser::findCachedModelsFromQuery( $this );
            }
        }

        return parent::get($columns);
    }
}