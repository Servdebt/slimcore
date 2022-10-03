<?php

namespace Servdebt\SlimCore\Utils;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\Grammar;
use Illuminate\Database\Query\Processors\Processor;

class QueryBuilder extends Builder
{

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


	public function compareNumeric($column, $value, string $conditionType = 'and'): self
    {
		$operator = $this->extractOperator($value);

		$value = str_replace([' ', '€', '$', '%'], '', $value);
		$value = str_replace(',', '.', $value);
		$value = preg_replace('/\.(?=.*\.)/', '', $value);

		if (is_numeric($value)) {
			$this->where($column, $operator, $value, strtolower($conditionType));
		}

		return $this;
	}


	public function compareDate($column, $startDate = null, $endDate = null, string $conditionType = 'and'): self
    {
		if (isset($startDate) && strlen($startDate) > 0) {

			$operator = $this->extractOperator($startDate);

			$endDate = $endDate !== null && strlen($endDate) > 0 ? $endDate : $startDate;

			$datetimeFormatIni = '0000-01-01 00:00:00';
			if (strlen($endDate) < 10) {
				$datetimeFormatEnd = date("Y-".(strlen($endDate) < 7 ? "12" : "m")."-t 23:59:59", strtotime($endDate));
			}
			else {
				$datetimeFormatEnd = '0000-12-31 23:59:59';
			}

			$startDate = \DateTime::createFromFormat('Y-m-d H:i:s', $startDate.substr($datetimeFormatIni, strlen($startDate), strlen($datetimeFormatIni)));
			$endDate = \DateTime::createFromFormat('Y-m-d H:i:s', $endDate.substr($datetimeFormatEnd, strlen($endDate), strlen($datetimeFormatEnd)));

			if ($operator != "=") {
				$this->where($column, $operator, $startDate->format('Y-m-d H:i:s'), strtolower($conditionType));
			}

			if ($startDate !== false && $endDate !== false) {
				$this->whereBetween($column, [
					$startDate->format('Y-m-d H:i:s'),
					$endDate->format('Y-m-d H:i:s')
				], strtolower($conditionType));
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

		return $this->get()->toArray();
	}


    /**
     * @param $valueField
     * @param null $keyField
     * @return array
     */
	public function columnsToArray($valueField, $keyField = null): array
    {
		return array_column($this->toArray(), $valueField, $keyField);
	}


    /**
     * Example: \Lib\QueryBuilder::getData('Users', ['UserID', 'Name'], ['empty' => true])
     * @param string $table
     * @param array $columns
     * @param array $conditions
     * @return array
     */
    public static function getData(string $table, array $columns, array $conditions = []): array
    {
        $qb = (new self())->from($table)->selectRaw(implode(',',$columns));

        if (isset($conditions['join'])) {
            $qb->join($conditions['join'][0], $conditions['join'][1]);
        }

        if (isset($conditions['where'])) {
            $qb->whereRaw($conditions['where']);
        }

        if (isset($conditions['order'])) {
            $qb->orderByRaw($conditions['where']);
        }

        $res = $qb->pluck($columns[1] ?? $columns[0], $columns[0])->toArray();

        if (isset($conditions['empty']) && $conditions['empty']) {
            $res = ['' => ''] + $res;
        }

        return $res;
    }


    /**
     * @param string $string
     * @return string
     */
    private function extractOperator(mixed &$string): string
    {
        $string = trim($string);
        $operator = '=';
        if (str_starts_with($string, '<')) $operator = '<';
        elseif (str_starts_with($string, '<=')) $operator = '<=';
        elseif (str_starts_with($string, '>')) $operator = '>';
        elseif (str_starts_with($string, '>=')) $operator = '>=';
        $string = trim(str_replace($operator, "", $string));

        return $operator;
    }

}