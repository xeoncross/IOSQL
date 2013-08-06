<?php
/**
 * Provides a database wrapper around the PDO service to help reduce the effort
 * to interact with a RDBMS such as SQLite, MySQL, or PostgreSQL.
 *
 * @author		David Pennington
 * @copyright	(c) 2013 davidpennington.me
 * @license		MIT License <http://www.opensource.org/licenses/mit-license.php>
 ********************************** 80 Columns *********************************
 */
namespace IOSQL;

class Database
{
	public $i = '`', $c, $driver;
	static $queries = array();

	/**
	 * Set the database connection on creation. This allows us to use
	 * [dependency injection](http://en.wikipedia.org/wiki/Dependency_injection)
	 * to support multiple database wrappers to different RDBMS.
	 *
	 * @param object $pdo PDO connection object
	 */
	public function __construct(\PDO $pdo)
	{
	    // Set prepared statement emulation depending on server version
	    $version = $pdo->getAttribute(\PDO::ATTR_SERVER_VERSION);

	    $pdo->setAttribute(
	    	\PDO::ATTR_EMULATE_PREPARES,
	    	version_compare($version, '5.1.17', '<')
	    );

		$this->c = $pdo;
		$this->driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

		switch($this->driver)
		{
			case 'pgsql':
			case 'sqlsrv':
			case 'dblib':
			case 'mssql':
			case 'sybase':
				$this->i = '"';
				break;
			case 'mysql':
			case 'sqlite':
			case 'sqlite2':
			default:
				$this->i = '`';
		}
	}

	/**
	 * Fetch a column offset from the result set (COUNT() queries)
	 *
	 * @param string $query query string
	 * @param array $params query parameters
	 * @param integer $key index of column offset
	 * @return array|null
	 */
	public function column($query, $params = NULL, $key = 0)
	{
		if($statement = $this->query($query, $params))
			return $statement->fetchColumn($key);
	}

	/**
	 * Fetch a single query result row
	 *
	 * @param string $query query string
	 * @param array $params query parameters
	 * @return mixed
	 */
	public function row($query, $params = NULL)
	{
		if($statement = $this->query($query, $params))
			return $statement->fetch();
	}

	/**
	 * Fetches an associative array of all rows as key-value pairs (first
	 * column is the key, second column is the value).
	 *
	 * @param string $query query string
	 * @param array $params query parameters
	 * @return array
	 */
	public function pairs($query, $params = NULL)
	{
		$data = array();

		if($statement = $this->query($query, $params))
			while($row = $statement->fetch(\PDO::FETCH_NUM))
				$data[$row[0]] = $row[1];

		return $data;
	}

	/**
	 * Fetch all query result rows
	 *
	 * @param string $query query string
	 * @param array $params query parameters
	 * @param int $column the optional column to return
	 * @return array
	 */
	public function fetch($query, $params = NULL, $column = NULL)
	{
		if( ! $statement = $this->query($query, $params)) return;

		// Return an array of records
		if($column === NULL) return $statement->fetchAll();

		// Fetch a certain column from all rows
		return $statement->fetchAll(\PDO::FETCH_COLUMN, $column);
	}

	/**
	 * Prepare and send a query returning the PDOStatement
	 *
	 * @param string $query query string
	 * @param array $params query parameters
	 * @return object|null
	 */
	public function query($query, $params = NULL)
	{
		$statement = $this->c->prepare(static::$queries[] = strtr($query, '`', $this->i));
		$statement->execute( (array) $params);
		return $statement;
	}

	/**
	 * Insert a row into the database
	 *
	 * @param string $table name
	 * @param array $data
	 * @param string $column The name of the primary key column
	 * @return integer|null
	 */
	public function insert($table, array $data, $column = 'id')
	{
		$query = "INSERT INTO `$table` (`" . implode('`, `', array_keys($data))
			. '`) VALUES (' . rtrim(str_repeat('?, ', count($data = array_values($data))), ', ') . ')';

		return $this->driver == 'pgsql'
			? $this->column($query . " RETURNING `$column`", $data)
			: ($this->query($query, $data) ? $this->c->lastInsertId() : NULL);
	}

	/**
	 * Update a database row
	 *
	 * @param string $table name
	 * @param array $data
	 * @param integer $pk The primary key
	 * @param string $column The name of the primary key column
	 * @return integer|null
	 */
	public function update($table, $data, $pk, $column = 'id')
	{
		$keys = implode('`= ?, `', array_keys($data));
		$query = "UPDATE `$table` SET `$keys` = ? WHERE `$column` = ?";

		if($statement = $this->query($query, array_values($data + array($pk))))
		{
			return $statement->rowCount();
		}
	}

	/**
	 * Issue a delete query
	 *
	 * @param string $table name
	 * @param integer $pk The primary key
	 * @param string $column The name of the primary key column
	 * @return integer|null
	 */
	function delete($table, $pk, $column = 'id')
	{
		if($statement = $this->query("DELETE FROM `$table` WHERE `$column` = ?", $pk))
		{
			return $statement->rowCount();
		}
	}

	/**
	 * Return a schema object for the current database connection
	 *
	 * @return Schema
	 */
	public function getSchema()
	{
		switch($this->driver)
		{
			case 'pgsql':
				return new Schema\PostgreSQL($this->c, $this->i);
			case 'mysql':
				return new Schema\MySQL($this->c, $this->i);
			case 'sqlite':
				return new Schema\SQLite($this->c, $this->i);
			default:
				throw new Exception('A Migration driver for ' . $this->driver . ' has not be created');
		}
	}


	/*
	 * The following functions could all be wrapped in a __call() if we didn't need IDE support
	 */


	/**
	 * Escapes dangerous characters in string so it can be used in a raw SQL
	 * query. Instead of quoting values it is recomended that you use prepared
	 * statements.
	 *
	 * @param mixed $value to quote
	 * @return string
	 */
	public function quote($value)
	{
		return $this->c->quote($value);
	}

	/**
	 * Execute an SQL statement and return the number of affected rows
	 *
	 * @param string $sql
	 * @return int
	 */
	public function execute($sql)
	{
		return $this->c->exec($sql);
	}

	/**
	 * Initiates a transaction
	 *
	 * @return boolean
	 */
	public function beginTransaction()
	{
		return $this->c->beginTransaction();
	}

	/**
	 * Commits a transaction
	 *
	 * @return boolean
	 */
	public function commit()
	{
		$this->c->commit();
	}

	/**
	 * Rolls back a transaction
	 *
	 * @return boolean
	 */
	public function rollBack()
	{
		$this->c->rollBack();
	}
}
