<?php
/**
 * Provides a database record commit manager to save round trips
 *
 * @author		David Pennington
 * @copyright	(c) 2013 davidpennington.me
 * @license		MIT License <http://www.opensource.org/licenses/mit-license.php>
 ********************************** 80 Columns *********************************
 */
namespace IOSQL;

class Manager {

	public $models = array();
	public $db = null;

	public function __construct(Database $db)
	{
		$this->db = $db;
	}

	public function persist(Model $model, $key, $value)
	{
		$hash = spl_object_hash($model);

		if(empty($this->models[$hash])) {
			$this->models[$hash] = array('model' => $model, 'values' => array($key => $value));
		} else {
			$this->models[$hash]['values'][$key] = $value;
		}

		return $this;
	}

	public function flush()
	{
		foreach($this->models as $hash => $data)
		{
			if($data['model']->pk()) {
				$this->update($data['values'], $data['model']);
			} else {
				$this->insert($data['values'], $data['model']);
			}
			//print json_encode($data['values']);
			//$data['model']->pk($hash + 1);
		}

		$this->models = array();
	}

	public function reset()
	{
		$this->models = array();
		return $this;
	}

	/**
	 * Insert the current object into the database table.
	 *
	 * @param array $data The data to insert
	 * @param Model $model The model instance
	 * @return integer
	 */
	protected function insert(array $data, Model $model)
	{
		//return $model->pk(spl_object_hash($model));

		$id = $this->db->insert($model->table, $data);
		$model->pk($id);
		return $id;
	}


	/**
	 * Update the current object in the database.
	 *
	 * @param array $data The data to update
	 * @param Model $model The model instance
	 * @return boolean
	 */
	protected function update(array $data, Model $model)
	{
		//return;

		$result = $this->db->update($model->table, $data, $model->pk(), $model::$key);

		if($model::$cache) {
			$model::$cache->delete($model->table . $model->pk());
		}

		return $result;
	}

	/**
	 * Auto-commit any remaining changes before we shutdown
	 */
	public function __destruct()
	{
		$this->flush();
	}
}