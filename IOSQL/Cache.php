<?php
/**
 * Provides row-level cache support (to be extended)
 *
 * @author		David Pennington
 * @copyright	(c) 2013 davidpennington.me
 * @license		MIT License <http://www.opensource.org/licenses/mit-license.php>
 ********************************** 80 Columns *********************************
 */
namespace IOSQL;

class Cache
{
	/**
	 * Store a value in the cache
	 *
	 * @param string $key name
	 * @param mixed $value to store
	 */
	public function set($key, $value){}


	/**
	 * Fetch a value from the cache
	 *
	 * @param string $key name
	 * @return mixed
	 */
	public function get($key){}


	/**
	 * Delete a value from the cache
	 *
	 * @param string $key name
	 * @return boolean
	 */
	public function delete($key){}


	/**
	 * Check that a value exists in the cache
	 *
	 * @param string $key name
	 * @return boolean
	 */
	public function exists($key){}
}