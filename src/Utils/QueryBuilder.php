<?php

namespace Servdebt\SlimCore\Utils;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\Grammar;
use Illuminate\Database\Query\Processors\Processor;

class QueryBuilder extends Builder
{

    public array $lazyLoads = [];

    public function __construct(ConnectionInterface $connection = null, Grammar $grammar = null, Processor $processor = null)
    {
        /** ConnectionInterface $connection */
        if ($connection == null) $connection = app()->resolve('db');

        parent::__construct($connection, $grammar, $processor);
    }


    public function compareString($column, $value, string $conditionType = 'and'): self
    {
        if (!empty($value)) {
            $this->where($column, "like", "%{$value}%", strtolower($conditionType));
        }

        return $this;
    }


    public function compareBoolean($column, $value, string $conditionType = 'and'): self
    {
        if ($value !== "") {
            $this->where($column, "=", (int)$value, strtolower($conditionType));
        }

        return $this;
    }


    public function compareInt($column, $value, string $conditionType = 'and'): self
    {
        $operator = $this->extractOperator($value);
        $value = $this->formatNumerics($value);

        if (is_numeric($value) && (int)$value == (float)$value) {
            $this->where($column, $operator, (int)$value, strtolower($conditionType));
        }

        return $this;
    }


    public function compareNumeric($column, $value, string $conditionType = 'and'): self
    {
        $operator = $this->extractOperator($value);
        $value = $this->formatNumerics($value);

        if (is_numeric($value)) {
            $this->where($column, $operator, (float)$value, strtolower($conditionType));
        }

        return $this;
    }


    public function compareDate($column, $startDate = null, $endDate = null, string $conditionType = 'and', $minYear = 1900): self
    {
        if (isset($startDate) && strlen($startDate) > 0) {

            $operator = $this->extractOperator($startDate);
            $endDate = $endDate !== null && strlen($endDate) > 0 ? $endDate : $startDate;

            try {
                $dtStart = new \DateTime($startDate . substr("{$minYear}-01-01 00:00:00", strlen($startDate), 19));
                $dtEnd = (new \DateTime($endDate . substr("{$minYear}-12-01 23:59:59", strlen($endDate), 19)));
                if (strlen($endDate) < 8) $dtEnd->modify('last day of this month');

            } catch (\Exception $e) {
                $dtStart = $dtEnd = new \DateTime("{$minYear}-01-01 00:00:00");
            }

            if ($dtStart->format('Y') < $minYear) $dtStart->setDate($minYear, $dtStart->format('m'), $dtStart->format('d'));
            if ($dtEnd->format('Y') < $minYear) $dtEnd->setDate($minYear, $dtEnd->format('m'), $dtEnd->format('d'));

            if ($operator != "=") {
                $this->where($column, $operator, $dtStart->format('Y-m-d H:i:s'), strtolower($conditionType));
            } else {
                $this->whereBetween($column, [$dtStart->format('Y-m-d H:i:s'), $dtEnd->format('Y-m-d H:i:s')], strtolower($conditionType));
            }
        }

        return $this;
    }


    public function addConditionIfValue($condition, $value = null, array $params = [], string $conditionType = 'and'): self
    {
        if (isset($value) && !empty($value)) {
            $this->whereRaw($condition, $params, strtolower($conditionType));
        }

        return $this;
    }


    public function lazyLoad(string $relationName, string $key, string $query): self
    {
        $this->lazyLoads[$relationName] = ['key' => $key, 'query' => $query];

        return $this;
    }


    public function execLazyLoads(array $data): array
    {
        if (empty($data) || empty($this->lazyLoads)) {
            return $data;
        }

        foreach ($this->lazyLoads as $relationName => $ll) {
            $query = $ll['query'];
            preg_match_all('/\{\{(.*?)\}\}/s', $query, $matches);

            // replace query placeholders
            foreach ($matches[1] as $match) {
                $ids = array_filter(array_unique(array_column($data, $match)), fn($id) => !is_null($id) && $id !== ''); // remove empty vals
                if (empty($ids)) $ids = [0];
                $query = str_replace('{{'.$match.'}}', "(".implode(',', $ids).")", $query);
            }

            $res = $this->connection->select($query);
            foreach ($data as &$dataLine) {
                $dataLine->{$relationName} = array_values(array_filter($res, function ($elem) use($dataLine, $ll) {
                    return $elem->{$ll['key']} == $dataLine->{$ll['key']};
                }));
            }
        }

        return array_values($data);
    }


    public function datatablesGetData(array $params): array
    {
        $this->offset($params['start'])
            ->limit($params['length']);

        foreach ($params['order'] ?? [] as $order) {

            $orderField = $params['columns'][$order['column']]['data'];
            $orderDirection = $order['dir'];

            if (!empty($orderField)) {
                $this->orderBy($orderField, $orderDirection);
            }
        }

        $data = $this->get()->toArray();

        return $this->execLazyLoads($data);
    }


    public function columnsToArray($valueField, $keyField = null): array
    {
        return array_column($this->toArray(), $valueField, $keyField);
    }


    public static function getData(string $table, string|array $columns, array $conditions = [], string $groupResults = ''): array
    {
        if (is_string($columns)) $columns = explode(',', $columns);

        $qb = (new self())->from($table)->selectRaw(
            (array_search('distinct', $conditions, true) !== false ? 'distinct ' : '').implode(',',$columns)
        );

        if (isset($conditions['join'])) {
            if (count($conditions['join']) == count($conditions['join'], COUNT_RECURSIVE)) {
                $qb->join($conditions['join']['table'], $conditions['join']['first'], $conditions['join']['operator'], $conditions['join']['second'], $conditions['join']['type']);
            } else {
                foreach ($conditions['join'] as $join) {
                    $qb->join($join['table'], $join['first'], $join['operator'], $join['second'], $join['type']);
                }
            }
        }

        if (isset($conditions['where'])) {
            $qb->whereRaw($conditions['where']);
        }

        if (isset($conditions['limit'])) {
            $qb->limit($conditions['limit']);
        }

        if (isset($conditions['order'])) {
            $qb->orderByRaw($conditions['order']);
        }

        if (empty($groupResults)) {
            $res = $qb->pluck($columns[1] ?? $columns[0], $columns[0])->toArray();
            if (isset($conditions['empty']) && $conditions['empty']) {
                $res = ['' => ''] + $res;
            }
        } else{
            $res = [];
            $rows = $qb->get();
            //remove alias table
            $fieldNameID = explode('.',$columns[0]);
            $fieldNameValue = explode('.', $columns[1] ?? $columns[0]);
            if (is_string($groupResults)) {
                foreach ($rows as $row) {
                    if (!isset($res[$row->{$groupResults}])) {
                        $res[$row->{$groupResults}] = [];
                    }
                    $res[$row->{$groupResults} ?? ''][$row->{($fieldNameID[1] ?? $fieldNameID[0])}] = $row->{($fieldNameValue[1] ?? $fieldNameValue[0])};
                }
            }
        }

        return $res;
    }


    private function extractOperator(mixed &$string): string
    {
        $string = trim($string ?? '');
        $operator = '=';
        if (str_starts_with($string, '<=')) $operator = '<=';
        elseif (str_starts_with($string, '<')) $operator = '<';
        elseif (str_starts_with($string, '>=')) $operator = '>=';
        elseif (str_starts_with($string, '>')) $operator = '>';
        $string = trim(str_replace($operator, "", $string));

        return $operator;
    }


    private function formatNumerics($value): string
    {
        $value = str_replace([' ', '€', '$', '%'], '', $value);
        $value = str_replace(',', '.', $value);

        return (string)preg_replace('/\.(?=.*\.)/', '', $value);
    }

}