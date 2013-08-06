<?php
/**
 * Base ORM Model that handles the database manager, query class, cache, and 
 * object relationships.
 *
 * @author		David Pennington
 * @copyright	(c) 2013 davidpennington.me
 * @license		MIT License <http://www.opensource.org/licenses/mit-license.php>
 ********************************** 80 Columns *********************************
 */
namespace IOSQL;

class Model extends Cache {

	// object data, related, changed, loaded, saved
	public $table, $data = array(), $loaded, $saved;

	protected static $manager = null;
	public static $cache = null;

	public static $key = 'id';
	public static $foreign_key = '%s_id';

	public static $created = 'created_on';
	public static $modified = 'modified_on';

	/**
	 * Create a new database entity object
	 *
	 * @param int|mixed $id of the row or row object
	 */
	public function __construct($table, $data = NULL)
	{
		$this->table = $table;
		
		if( ! $data) return;

		// Must be the record's Primary Key
		if(is_numeric($data))
		{
			$this->data[static::$key] = $data;
		}
		else
		{
			$this->hydrate($data);
		}

		$this->saved = 1;
	}

	/**
	 * Populate the current object with the values given
	 *
	 * @param array $values
	 */
	public function hydrate(array $values)
	{
		/*if(empty($values[static::$key])) {
			throw new \Exception('Cannot hydrate object without primary key');
		}*/

		$this->data = (array) $values;
		$this->loaded = 1;
		$this->saved = 1;
	}

	/**
	 * Get or set this object's primary key
	 *
	 * @param int $pk
	 * @return int
	 */
	public function pk($pk = NULL)
	{
		if($pk) {
			return $this->data[static::$key] = $pk;
		}

		if(isset($this->data[static::$key])) {
			return $this->data[static::$key];
		}
	}

	public static function manager(Manager $manager = NULL)
	{
		if($manager) {
			return static::$manager = $manager;
		}

		return static::$manager;
	}

	public static function cache(Cache $cache = NULL)
	{
		if($cache) {
			return static::$cache = $cache;
		}

		return static::$cache;
	}
	
	public function clear()
	{
		$this->data = array();
	}
	
	/**
	 * Attempt to load the object record from the database
	 *
	 * @return boolean
	 */
	public function load($where = NULL)
	{
		$key = static::$key;

		if($where)
		{
			if(is_numeric($where))
			{
				$where = array(static::$key, '=', $where);
			}

			// Find the record primary key in the database
			$id = Query::create(static::$key, $this->table)->where($where)->column();

			if( ! $id)
			{
				$this->clear();
				return FALSE;
			}

			$this->data[$key] = $id;
		}
		else
		{
			// Did we already load this object?
			if($this->loaded) return TRUE;

			if(empty($this->data[$key]))
			{
				return FALSE;
			}

			// Use the record primary key given in constructor
			$id = $this->data[$key];
		}

		// First check the cache
		if(!($row = static::$cache->get($this->table . $id)))
		{
			// Then get from the database and cache
			if($row = Query::create('*', $this->table)->where($key, '=', $id)->one(false));
			{
				static::$cache->set($this->table . $id, $row);
			}
		}

		if($row)
		{
			$this->data = (array) $row;
			return $this->saved = $this->loaded = TRUE;
		}
		else
		{
			$this->clear();
		}
	}

	/**
	 * Set a propery of this object
	 *
	 * @param string $key name
	 * @param mixed $value value
	 */
	public function __set($key, $value)
	{
		if( ! array_key_exists($key, $this->data) OR $this->data[$key] !== $value)
		{
			$this->data[$key] = $value;
			static::$manager->persist($this, $key, $value);
			$this->saved = 0;
		}
	}

	/**
	 * Retive a property or 1-to-1 object relation. Try to avoid loading the
	 * full object if they are only asking for the primary key.
	 *
	 * @param string $key the column or relation name
	 * @return mixed
	 */
	public function __get($key)
	{
		// All this to get the primary key without loading the entity
		if(isset($this->data[static::$key]))
		{
			if($key == static::$key) return $this->data[static::$key];
			if( ! $this->loaded) $this->load();
		}

		if(array_key_exists($key, $this->data))
		{
			return $this->data[$key];
		}
	}


	public function has($table)
	{
		$where = array(sprintf(static::$foreign_key, $this->table), '=', $this->pk());
		$object = new self($table);
		return ($object->load($where) ? $object : NULL);
	}


	public function belongsTo($table, $fk = NULL)
	{
		if( ! $fk) {
			$fk = sprintf(static::$foreign_key, $table);
		}

		$where = array(sprintf(static::$key, $table), '=', $this->$fk);
		$object = new static($table);
		return ($object->load($where) ? $object : NULL);
	}


	public function hasMany($table)
	{
		$query = new Query($table);
		$query->select(static::$key);
		return $query->where(sprintf(static::$foreign_key, $this->table), '=', $this->pk());
	}

	/**
	 * Load a has_many_through relation set
	 *
	 * @param string $m alias name
	 * @param mixed $a arguments to pass
	 * @return array
	 */
	public function __call($alias, $args)
	{
		$alias = strtolower($alias);

		//$model->clubThrough('user');
		//$model->clubThroughUser();

		$query = new Query($args[0]);

		// Notice that position 0 and false are both invalid results
		if( ! ($pos = strpos($alias, 'through')))
		{
			throw new \Exception ("\"$alias\" relation not found");
		}

		// Table $one through table $two
		//list($one, $junk) = explode('through', $alias, 2);
		$one = substr($alias, 0, $pos);
		$two = $args[0];

		$fk = sprintf(static::$foreign_key, $one);
		$where = array(sprintf(static::$foreign_key, $this->table), '=', $this->pk());

		$join_key = empty($args[1]) ? $two.'.'.$fk : $args[1];

		$query = new Query($one);
		$query->select($one.'.'.static::$key)
				->join($two, $one.'.'.static::$key, '=', $join_key)
				->where(sprintf(static::$foreign_key, $this->table), '=', $this->pk());

		return $query;
	}


	/**
	 * Alias for manually creating a new query object
	 */
	public static function __callStatic($table, $arguments)
    {
		return new Query($table);
    }

    /**
     * Save all changes to the database
     */
    public function save()
    {
    	$this->manager()->flush();
    }
}