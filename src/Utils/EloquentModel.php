<?php

namespace Servdebt\SlimCore\Utils;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model as BaseEloquentModel;
use Illuminate\Database\Eloquent\Builder;

class EloquentModel extends BaseEloquentModel
{

    protected $connection = 'db';

    public $timestamps = false;

    public array $autoFillColumns = [];

    public array $defaultValues = [];

    public array $setNullOnEmpty = [];


    public function __construct(array $attributes = [])
    {
        if (!$this->exists) {
            $this->attributes = array_merge($this->attributes, $this->defaultValues);
        }

        parent::__construct($attributes);
    }


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


    public function setAttribute($key, $value)
    {
        if (in_array($key, $this->setNullOnEmpty) && is_string($value) && trim($value) === '') {
            $value = null;
        }

        return parent::setAttribute($key, $value);
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
        $this->autoFillColumnsSetValues();

        // validation
        if ($validate && method_exists($this, 'getValidator')) {
            $this->getValidator($scenario)->assert($this->getAttributes());
        }

        return parent::save($options);
    }


    private function autoFillColumnsSetValues()
    {
        $UserID = app()->isConsole() ? app()->getConfig('app.slsAdminUserID') : (Session::get('user')['UserID'] ?? null);

        if (!$this->exists && in_array('DateCreated', $this->autoFillColumns) && $this->DateCreated == null) {
            $this->setAttribute('DateCreated', date('Y-m-d H:i:s'));
        }
        if (!$this->exists && in_array('CreatedByUserID', $this->autoFillColumns) && $this->CreatedByUserID == null) {
            $this->setAttribute('CreatedByUserID', $UserID);
        }
        if ($this->exists && in_array('DateUpdated', $this->autoFillColumns) && $this->DateUpdated == null) {
            $this->setAttribute('DateUpdated', date('Y-m-d H:i:s'));
        }
        if ($this->exists && in_array('UpdatedByUserID', $this->autoFillColumns) && $this->UpdatedByUserID == null) {
            $this->setAttribute('UpdatedByUserID', $UserID);
        }

        if (!$this->exists && in_array('CreationDate', $this->autoFillColumns) && $this->CreationDate == null) {
            $this->setAttribute('CreationDate', date('Y-m-d H:i:s'));
        }
        if (!$this->exists && in_array('CreatedBy', $this->autoFillColumns) && $this->CreatedBy == null) {
            $this->setAttribute('CreatedBy', $UserID);
        }
    }

}