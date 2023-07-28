<?php

namespace Servdebt\SlimCore\Utils;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model as BaseEloquentModel;
use Illuminate\Database\Eloquent\Builder;

class EloquentModel extends BaseEloquentModel
{

    protected $connection = 'db';

    public $timestamps = false;

    public array $setNullOnEmpty = [];


    /**
     * Get a new query builder instance for the connection.
     *
     * @return QueryBuilder
     */
    protected function newBaseQueryBuilder() : QueryBuilder
    {
        $conn = $this->getConnection();
        $grammar = $conn->getQueryGrammar();

        return new QueryBuilder($conn, $grammar, $conn->getPostProcessor());
    }


    /**
     * Execute a query for a single record by ID.
     *
     * @param  mixed  single string or array $ids like [column => value].
     * @param array $columns
     * @return ?object
     */
    protected function find($id, array $columns = ['*']) : ?object
    {
        $query = $this->newQuery();
        $query->select($columns);

        if (!is_array($id)) {
            return $query->where($this->getKeyName(), '=', $id)->first();
        }

        foreach ($id as $key => $val) {
            $query->where($key, '=', $val);
        }
        return $query->first();
    }


    public function fill(array $attributes)
    {
        foreach ($attributes as $key => &$val) {
            $attributes[$key] = $val === '' ? null : $val;
        }

        parent::fill($attributes);
    }


    /**
     * Set the keys for a save update query. multiple pk support
     *
     * @param Builder $query
     * @return Builder
     */
    protected function setKeysForSaveQuery($query): Builder
    {
        $keys = $this->getKeyName();

        if (!is_array($keys)) {
            return parent::setKeysForSaveQuery($query);
        }

        foreach ((array)$this->getKeyName() as $key) {
            // UPDATE: Added isset() per devflow's comment.
            if (isset($this->$key)) {
                $query->where($key, '=', $this->$key);
            } else {
                throw new \Exception(__METHOD__ . 'Missing part of the primary key: ' . $key);
            }
        }

        return $query;
    }


    protected function asDateTime(mixed $value): ?Carbon
    {
        return empty($value) ? null : parent::asDateTime($value);
    }


    protected function asDate(mixed $value): ?Carbon
    {
        return empty($value) ? null : parent::asDate($value);
    }


    public function save(array $options = [], bool $validate = true, string $scenario = 'default'): bool
    {
        // set fields DateCreated/Updated Created/UpdatedByUserID
        $dtField = !$this->exists ? 'DateCreated' : 'DateUpdated';
        $userField = !$this->exists ? 'CreatedByUserID' : 'UpdatedByUserID';
        $userid = app()->isConsole() ? app()->getConfig('app.slsAdminUserID') : Session::get('user')['UserID'] ?? null;

        if (array_key_exists($dtField, $this->attributes)) {
            $this->attributes[$dtField] = date('Y-m-d H:i:s');
        }
        if (array_key_exists($userField, $this->attributes)) {
            $this->attributes[$userField] = $userid;
        }

        // validation
        if ($validate && method_exists($this, 'getValidator')) {
            $this->getValidator()->assert($this->getAttributes());
        }

        return parent::save($options);
    }

}
