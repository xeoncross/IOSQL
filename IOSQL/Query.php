<?php
/**
 * Handles generating SQL
 *
 * @author		David Pennington
 * @copyright	(c) 2013 davidpennington.me
 * @license		MIT License <http://www.opensource.org/licenses/mit-license.php>
 ********************************** 80 Columns *********************************
 */
namespace IOSQL;

class Query
{
	public static $db;
	public $table;
	public $columns = array('*');
	public $distinct;
	public $join;
	public $where;
	public $having;
	public $limit;
	public $offset = 0;
	public $orderBy;
	public $groupBy;

	public static function create($columns, $from)
	{
		$self = new self($from);
		return $self->select($columns);
	}

	public function __construct($table)
	{
		$this->table = $table;
	}

	public function select($columns)
	{
		$this->columns = (array) $columns;
		return $this;
	}

	public function distinct($on = true)
	{
		$this->distinct = $on;
		return $this;
	}

	public function from($table)
	{
		$this->table = $table;
		return $this;
	}

	public function limit($limit)
	{
		$this->limit = (int) $limit;
		return $this;
	}

	public function offset($offset)
	{
		$this->offset = (int) $offset;
		return $this;
	}

	public function join($table, $column1, $operator, $column2, $type = 'LEFT')
	{
		//$this->join[] = func_get_args();
		$this->join[$table] = array($table, $column1, $operator, $column2, $type);
		return $this;
	}

	/**
	 * Add a where condition to the query.
	 *
	 * @param  string  $column
	 * @param  string  $operator
	 * @param  mixed   $value
	 * @param  string  $connector
	 * @return Query
	 */
	public function where($column, $operator = null, $value = null, $connector = 'AND')
	{
		if(is_array($column))
		{
			if(is_array($column[0]))
			{
				foreach($column as $condition)
				{
					list($column, $operator, $value, $connector) = $condition + array(null, null, null, $connector);
					$this->where($column, $operator, $value, $connector);
				}
				return $this;
			}

			list($column, $operator, $value, $connector) = $column + array(null, null, null, $connector);
		}

		$this->where[] = array($column, $operator, $value, $connector);
		return $this;
	}

	/**
	 * Create the ORDER BY clause for MySQL and SQLite (still working on PostgreSQL)
	 *
	 * @param array $fields to order by
	 */
	public function orderBy($column, $sort = 'DESC')
	{
		//$this->orderBy[] = func_get_args();
		if( ! is_array($column))
		{
			$column = array($column => $sort);
		}

		foreach($column as $field => $sort)
		{
			$this->orderBy[$field] = $sort;
		}

		return $this;
	}
	/**
	 * Create the GROUP BY clause for MySQL and SQLite (still working on PostgreSQL)
	 *
	 * @param array $fields to order by
	 */
	public function groupBy($column)
	{
		$this->groupBy = $column;

		return $this;
	}

	public function parse()
	{
		$sql = $this->distinct ? 'SELECT DISTINCT' : 'SELECT';

		$columns = join(", ", $this->columns);

		if(strpos($columns, '(') === false) {

			$columns = join("`, `", $this->columns);

			if(strpos($columns, '(') === false)
			{
				$columns = "`$columns`";
			}
		}

		$sql .= " $columns FROM `{$this->table}` ";

		if($this->join)
		{
			foreach($this->join as $join)
			{
				list($table, $column1, $operator, $column2, $type) = $join;
				$sql .= "$type JOIN `$table` ON `$column1` $operator `$column2` ";
			}
		}

		// Process WHERE conditions
		$params = array();
		if($this->where)
		{
			$sql .= "WHERE ";
			foreach($this->where as $i => $where)
			{
				list($column, $operator, $value, $connector) = $where;

				if($i > 0) $sql .= "$connector ";

				if($operator)
				{
					$sql .= "`$column` $operator ? ";
					$params[] = $value;
				}
				else
				{
					$sql .= $column . ' ';
				}
			}
		}

		// Optional grouping
		if($this->groupBy)
		{
			$sql .= ' GROUP BY ' . $this->groupBy;
		}

		// Optional sorting
		if($this->orderBy)
		{
			$sql .= ' ORDER BY ';

			foreach($this->orderBy as $column => $sort)
			{
				$sql .= "`$column` $sort, ";
			}

			// Remove ending ", "
			$sql = substr($sql, 0, -2);
		}

		if($this->limit)
		{
			$sql .= " LIMIT {$this->limit} OFFSET {$this->offset}";
		}

		// Make sure column and table names are quoted correctly
		$sql = str_replace(array('.', '`*`'), array("`.`", '*'), $sql);

		return array($sql, $params);
	}

	public function column()
	{
		list($sql, $params) = $this->limit(1)->parse();

		$column = self::$db->column($sql, $params);

		return $column;
	}

	public function count()
	{
		list($sql, $params) = $this->select('COUNT(*)')->limit(1)->parse();

		$column = static::$db->column($sql, $params);

		return $column;
	}

	public function one($object = true)
	{
		list($sql, $params) = $this->limit(1)->parse();

		$row = static::$db->row($sql, $params);

		if( ! $row) {
			return;
		}

		return $object ? new Model($this->table, $row) : $row;
	}


	public function pairs()
	{
		list($sql, $params) = $this->parse();

		$results = self::$db->pairs($sql, $params);

		return $results;
	}


	public function fetch()
	{
		list($sql, $params) = $this->parse();

		$results = self::$db->fetch($sql, $params);

		foreach($results as $id => $row)
		{
			// Array of ID's
			if(count($row) === 1) $row = current($row);

			$results[$id] = new Model($this->table, $row);
		}

		return $results;
	}

	public function clear()
	{
		$this->table = NULL;
		$this->columns = array('*');
		$this->distinct = NULL;
		$this->join = NULL;
		$this->where = NULL;
		$this->having = NULL;
		$this->limit = NULL;
		$this->offset = 0;
		$this->orderBy = NULL;
		$this->groupBy = NULL;

		return $this;
	}
}
