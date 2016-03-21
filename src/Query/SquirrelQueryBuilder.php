<?php
namespace Eloquent\Cache\Query;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Eloquent\Cache\SquirrelCache;

/**
 * This class is used as a proxy to the back-end Eloquent ORM.  It allows
 * us to first check if we have cache moddels, before we actually do any queries 
 * to the database.
 * 
 */
class SquirrelQueryBuilder extends \Illuminate\Database\Query\Builder
{
    private $sourceModel;

    public function setSourceModel( Model $sourceModel )
    {
        $this->sourceModel = $sourceModel;
    }

    public function sourceModel()
    {
        return $this->sourceModel;
    }

    private function sourceObjectDeletedAtColumnName()
    {
        if( method_exists($this->sourceModel, 'getDeletedAtColumn') ) {
            return $this->sourceModel->getDeletedAtColumn();
        }
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
        if( $this->sourceModel->isCacheing() ) {
            $cachedModels = $this->findCachedModels();
            
            if( !empty($cachedModels) ) {
                return $cachedModels;
            }
        }

        $results = parent::get($columns);

        foreach( $results as $result ) {
            SquirrelCache::remember( $this->sourceModel, (array)$result );
        }

        return $results;
    }

    private function findCachedModels()
    {
        if( !$this->sourceModel ) {
            return false;
        }

        $uniqueKeys   = $this->sourceModel->getUniqueKeys();

        // Early validation allows us to fail immediately on obvious unsupported query types
        if( empty($uniqueKeys) || $this->distinct || $this->limit > 1 || 
            !empty($this->groups) || !empty($this->havings) || !empty($this->orders)  || !empty($this->offset)  || 
            !empty($this->unions) || !empty($this->joins)   || !empty($this->columns) ||  empty($this->wheres) ) {
            return false;
        }

        $query = new SquirrelQuery( $this->wheres, $this->sourceObjectDeletedAtColumnName() );

        $searchingKey   = $query->uniqueKeyString();
        $modelKeys      = SquirrelCache::uniqueKeys($this->sourceModel);
        $cacheKeyPrefix = SquirrelCache::getCacheKeyPrefix( get_class($this->sourceModel) );

        if( array_key_exists($searchingKey, $modelKeys) ) {
            $cacheKeys = $query->cacheKeys($cacheKeyPrefix);

            if( empty($cacheKeys) ) {
                return;
            }

            $models = [];
            foreach( $cacheKeys as $key ) {
                $model = SquirrelCache::get( $key );
                if( $model ) {
                    $models[] = (object)$model;
                } else {
                    return;
                }
            }

            return $models;
        }
    }
}