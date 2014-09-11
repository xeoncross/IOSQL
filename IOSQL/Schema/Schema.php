<?php
/**
 * Interface for database-specific schema wrappers
 *
 * @author		David Pennington
 * @copyright	(c) 2013 davidpennington.me
 * @license		MIT License <http://www.opensource.org/licenses/mit-license.php>
 ********************************** 80 Columns *********************************
 */
namespace IOSQL\Schema;

Abstract class Schema
{
	public $i = '`';
	public $pdo = NULL;
	public $driver = NULL;

	public function __construct(\PDO $pdo, $i = '`')
	{
		$this->i = $i;
		$this->pdo = $pdo;
		$this->driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
	}

	public static function column_options($options)
	{
		return $options + array(
			//'type' => 'primary|string|integer|boolean|decimal|datetime', REQUIRED!
			'type' => 'string',
			'length' => NULL,
			'null' => TRUE,
			'default' => NULL,
			'precision' => 0,
			'scale' => 0,
		);
	}

	/**
	 * Checks if a given table exists.
	 *
	 * @param   string  $table  Table name
	 * @return  bool
	 */
	public function table_exists($table)
	{
		try
		{
			$this->pdo->exec("SELECT * FROM `$table` LIMIT 1");
			return true;
		}
		catch (\Exception $e)
		{
			return false;
		}
	}


	public function create_table($table, array $columns, $execute = TRUE)
	{
		$primary = 'id';
		
		foreach($columns as $name => $meta)
		{
			if($meta['type'] == 'primary')
			{
				$primary = $name;
			}

			$fields[] = $this->column($name, $meta);
		}

		$sql = sprintf($this->sql['create_table'], $table, join(",\n", $fields), $primary);

		return $execute ? $this->exec($sql) : $sql;
	}

	/**
	 * Cascade automatically drops objects that depend on the table while the
	 * default (restrict), will refuse to drop the table if any objects depend
	 * on it.
	 */
	public function drop_table($table, $type = 'CASCADE', $execute = TRUE)
	{
		$sql = sprintf($this->sql['drop_table'], $table, $type);

		return $execute ? $this->exec($sql) : $sql;
	}

	public function rename_table($table, $name, $execute = TRUE)
	{
		$sql = sprintf($this->sql['rename_table'], $table, $name);
		return $execute ? $this->exec($sql) : $sql;
	}

	public function create_column($table, $column, array $options = array(), $execute = TRUE)
	{
		$column = $this->column($column, self::column_options($options));
		$sql = sprintf($this->sql['create_column'], $table, $column);
		return $execute ? $this->exec($sql) : $sql;
	}

	public function drop_column($table, $column, $type = 'CASCADE', $execute = TRUE)
	{
		$sql = sprintf($this->sql['drop_column'], $table, $column, $type);
		return $execute ? $this->exec($sql) : $sql;
	}

	public function rename_column($table, $column, $name, $execute = TRUE)
	{
		$sql = sprintf($this->sql['rename_column'], $table, $column, $name);
		return $execute ? $this->exec($sql) : $sql;
	}

	public function create_foreign_key($table, $column, $foreign_table, $foreign_key, $execute = TRUE)
	{
		$sql = sprintf(
			$this->sql['create_foreign_key'],
			$table,
			$table . '_' . $column . '_fk',
			$column,
			$foreign_table,
			$foreign_key
		);

		return $execute ? $this->exec($sql) : $sql;
	}

	public function drop_foreign_key($table, $column, $type = 'CASCADE', $execute = TRUE)
	{
		$sql = sprintf(
			$this->sql['drop_foreign_key'],
			$table,
			$table . '_' . $column . '_fk',
			$type
		);

		return $execute ? $this->exec($sql) : $sql;
	}

	public function create_unique($table, $column, $execute = TRUE)
	{
		$sql = sprintf(
			$this->sql['create_unique'],
			$table,
			$table . '_' . $column . '_unique',
			$column
		);

		return $execute ? $this->exec($sql) : $sql;
	}

	public function drop_unique($table, $column, $type = 'CASCADE', $execute = TRUE)
	{
		$sql = sprintf(
			$this->sql['drop_unique'],
			$table,
			$table . '_' . $column . '_unique',
			$type
		);

		return $execute ? $this->exec($sql) : $sql;
	}

	public function create_index($table, $column, $execute = TRUE)
	{
		$sql = sprintf(
			$this->sql['create_index'],
			$table . '_' . $column . '_index',
			$table,
			$column
		);

		return $execute ? $this->exec($sql) : $sql;
	}

	public function drop_index($table, $column, $type = 'CASCADE', $execute = TRUE)
	{
		$sql = sprintf(
			$this->sql['drop_index'],
			$table . '_' . $column . '_index',
			$type
		);

		return $execute ? $this->exec($sql) : $sql;
	}

	/*
	 * Correctly quotes all column/table names before injecting them into the query
	 *
	public function build($sql, $args)
	{
		$args = func_get_args();
		array_shift($args);

		foreach($args as &$arg)
		{
			$arg = '"' . $arg . '"';
		}

		return vsprintf($this->sql[$sql], $args);
	}
	*/

	public function exec($sql)
	{
		if(PHP_SAPI == 'cli')
		{
			print $sql . "\n";
		}

		return $this->pdo->exec($sql);
	}
}
