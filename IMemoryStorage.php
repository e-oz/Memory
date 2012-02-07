<?php
namespace Jamm\Memory;

interface IMemoryStorage
{
	/**
	 * Add value to memory storage, only if this key does not exists (or false will be returned).
	 *
	 * @param string $k
	 * @param mixed $v
	 * @param int $ttl
	 * @param array|string $tags
	 * @return boolean
	 */
	public function add($k, $v, $ttl = 259200, $tags = NULL);

	/**
	 * Save variable in memory storage
	 *
	 * @param string $k		  - key
	 * @param mixed $v		   - value
	 * @param int $ttl		   - time to live (store) in seconds
	 * @param array|string $tags - array of tags for this key
	 * @return bool
	 */
	public function save($k, $v, $ttl = 259200, $tags = NULL);

	/**
	 * Read data from memory storage
	 *
	 * @param string|array $k (string or array of string keys)
	 * @param mixed $ttl_left = (ttl - time()) of key. Use to exclude dog-pile effect, with lock/unlock_key methods.
	 * @return mixed
	 */
	public function read($k, &$ttl_left = -1);

	/**
	 * Delete key or array of keys from storage
	 * @param string|array $k - keys
	 * @return boolean|array - if array of keys was passed, on error will be returned array of not deleted keys, or 'true' on success.
	 */
	public function del($k);

	/**
	 * Delete old (by ttl) variables from storage
	 * @return boolean
	 */
	public function del_old();

	/**
	 * Delete keys by tags
	 *
	 * @param array|string $tag - tag or array of tags
	 * @return boolean
	 */
	public function del_by_tags($tag);

	/**
	 * Select from storage via callback function
	 * Only values of 'array' type will be selected
	 * @param callback $fx ($value_array,$key)
	 * @param bool $get_array
	 * @return mixed
	 */
	public function select_fx($fx, $get_array = false);

	/**
	 * Increment value of the key
	 * @param string $key
	 * @param mixed $by_value
	 *							  if stored value is an array:
	 *							  if $by_value is a value in array, new element will be pushed to the end of array,
	 *							  if $by_value is a key=>value array, new key=>value pair will be added (or updated)
	 * @param int $limit_keys_count - maximum count of elements (used only if stored value is array)
	 * @param int $ttl			  - set time to live for key
	 * @return int|string|array new value of key
	 */
	public function increment($key, $by_value = 1, $limit_keys_count = 0, $ttl = 259200);

	/**
	 * Get exclusive mutex for key. Key will be still accessible to read and write, but
	 * another process can exclude dog-pile effect, if before updating the key he will try to get this mutex.
	 * @param mixed $key
	 * @param mixed $auto_unlocker_variable - pass empty, just declared variable
	 */
	public function lock_key($key, &$auto_unlocker_variable);

	/**
	 * Try to lock key, and if key is already locked - wait, until key will be unlocked.
	 * Time of waiting is defined in max_wait_unlock constant of MemoryObject class.
	 * @param string $key
	 * @param $auto_unlocker
	 * @return boolean
	 */
	public function acquire_key($key, &$auto_unlocker);

	/**
	 * Unlock key, locked by method 'lock_key'
	 * @param KeyAutoUnlocker $auto_unlocker
	 * @return bool
	 */
	public function unlock_key(KeyAutoUnlocker $auto_unlocker);

	/**
	 * @return array of all stored keys
	 */
	public function get_keys();

	/**
	 * @return string
	 */
	public function getLastErr();

	/**
	 * @return array
	 */
	public function get_stat();

	public function getErrLog();

	public function set_errors_triggering($errors_triggering = true);

	public function set_ID($ID);

	public function get_ID();
}
