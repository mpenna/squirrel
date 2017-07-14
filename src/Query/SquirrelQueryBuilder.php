<?php
namespace Eloquent\Cache\Query;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Eloquent\Cache\SquirrelCache;

/**
 * This class is used as a proxy to the back-end Eloquent ORM. It allows us
 * to first check if we have cache moddels before we actually send any
 * queries to the database.
 */
class SquirrelQueryBuilder extends Builder
{
    private $sourceModel;

    private $uniqueKeys;

    /**
     * Models with relationships may ultimately use this method to spawn
     * child queries. As such, we need to ensure we don't return a
     * SquirrelQueryBuilder object, as it will not work.
     *
     * @return \Illuminate\Database\Query\Builder
     */
    public function newQuery()
    {
        return new Builder($this->connection, $this->grammar, $this->processor);
    }

    /**
     * Setter method for setting the source model.
     *
     * @param Model $sourceModel
     */
    public function setSourceModel(Model $sourceModel)
    {
        $this->sourceModel = $sourceModel;
    }

    /**
     * Getter method for getting the source model.
     *
     * @return Model
     */
    public function sourceModel()
    {
        return $this->sourceModel;
    }

    /**
     * This method is overloaded from the base Elquent builder, so we can first
     * see if the cached models already exist, and we can then bypass checking
     * the database entirely.
     *
     * @access public
     * @param  array  $columns
     * @return array
     */
    public function get($columns = array('*'))
    {
        $cachedModels = $this->findCachedModels();

        if (!empty($cachedModels)) {
            return collect($cachedModels);
        }

        $results = parent::get($columns);

        if ($this->shouldUseCache()) {
            foreach ($results as $result) {
                SquirrelCache::remember($this->sourceModel, (array)$result);
            }
        }

        return $results;
    }

    /**
     * Helper method to get the source object's deleted_at column name.
     *
     * @return string
     */
    private function sourceObjectDeletedAtColumnName()
    {
        if ($this->sourceModel
            && method_exists($this->sourceModel, 'getDeletedAtColumn')) {
            return $this->sourceModel->getDeletedAtColumn();
        }
    }

    /**
     * Attempts to find cached models based on the current query.
     *
     * @return array
     */
    private function findCachedModels()
    {
        if (!$this->shouldUseCache()) {
            return false;
        }

        $this->uniqueKeys = $this->sourceModel->getUniqueKeys();

        // fail immediately on unsupported query types
        if ($this->isUnsupportedQuery()) {
            return false;
        }

        $query = new SquirrelQuery($this->wheres, $this->sourceObjectDeletedAtColumnName());

        $searchingKey   = $query->uniqueKeyString();
        $modelKeys      = SquirrelCache::uniqueKeys($this->sourceModel);
        $cacheKeyPrefix = SquirrelCache::getCacheKeyPrefix(get_class($this->sourceModel));

        if (array_key_exists($searchingKey, $modelKeys)) {
            $cacheKeys = $query->cacheKeys($cacheKeyPrefix);

            if (empty($cacheKeys)) {
                return;
            }

            $models = [];
            foreach ($cacheKeys as $key) {
                $model = SquirrelCache::get($key);

                if ($model) {
                    if ($deletedAt = $query->deletedAtObject()) {
                        if (array_key_exists($deletedAt->column, $model)) {
                            if ($deletedAt->value == SquirrelQueryWhere::WHERE_CLAUSE_TYPE_NULL
                                && !empty($model[$deletedAt->column])) {
                                // the query requires the deleted at column be empty
                                return;
                            }
                            if ($deletedAt->value == SquirrelQueryWhere::WHERE_CLAUSE_TYPE_NOT_NULL
                                && empty($model[$deletedAt->column])) {
                                // the query requires the deleted at column have a value
                                return;
                            }
                        } else {
                            // the deleted at column could not be found, so we are not going to return anything
                            return;
                        }
                    }

                    $models[] = (object)$model;
                    continue;
                }

                return;
            }

            return $models;
        }
    }

    private function isUnsupportedQuery()
    {
        return (
            empty($this->uniqueKeys)
            || $this->distinct
            || $this->limit > 1
            || !empty($this->groups)
            || !empty($this->havings)
            || !empty($this->orders)
            || !empty($this->offset)
            || !empty($this->unions)
            || !empty($this->joins)
            || !empty($this->columns)
            ||  empty($this->wheres)
        );
    }

    private function shouldUseCache()
    {
        return (
            $this->sourceModel
            && $this->sourceModel->isCacheing()
        );
    }
}
