<?php
/**
 * @author OZ
 * license MIT
 */

use Jamm\Memory\IMemoryStorage;
use Jamm\Memory\APCObject;
use Jamm\Memory\Key_AutoUnlocker;


/**
 * Do NOT use static class in new projects.
 * This class exists only for backward compatibility reasons.
 * @deprecated
 */
class Memory
{
	/** @var IMemoryStorage */
	private static $memory_object;

	/**
	 * @return IMemoryStorage
	 */
	public static function getMemoryObject()
	{
		if (empty(self::$memory_object))
		{
			self::$memory_object = new APCObject('memory');
		}
		return self::$memory_object;
	}

	/**
	 * Add value to memory storage, only if this key does not exists (or false will be returned).
	 *
	 * @param string $k
	 * @param mixed $v
	 * @param int $ttl
	 * @param array|string $tags
	 * @return boolean
	 */
	public static function add($k, $v, $ttl = 259200, $tags = NULL)
	{
		return self::getMemoryObject()->add($k, $v, $ttl, $tags);
	}

	/**
	 * Save variable in memory storage
	 *
	 * @param string $k - key
	 * @param mixed $v - value
	 * @param int $ttl - time to live (store) in seconds
	 * @param array|string $tags - array of tags for this key
	 * @return bool
	 */
	public static function save($k, $v, $ttl = 259200, $tags = NULL)
	{
		return self::getMemoryObject()->save($k, $v, $ttl, $tags);
	}

	/**
	 * Read data from memory storage
	 *
	 * @param string|array $k (string or array of string keys)
	 * @param mixed $ttl_left = (ttl - time()) of key. Use to exclude dog-pile effect, with lock/unlock_key methods.
	 * @return mixed
	 */
	public static function read($k = NULL, &$ttl_left = -1)
	{
		return self::getMemoryObject()->read($k, $ttl_left);
	}

	/**
	 * Delete key or array of keys from storage
	 * @param string|array $k - keys
	 * @return boolean|array - if array of keys was passed, on error will be returned array of not deleted keys, or 'true' on success.
	 */
	public static function del($k)
	{
		return self::getMemoryObject()->del($k);
	}

	/**
	 * Delete old (by ttl) variables from storage
	 *
	 * @return boolean
	 */
	public static function del_old()
	{
		return self::getMemoryObject()->del_old();
	}

	/**
	 * Delete keys by tags
	 *
	 * @param array|string $tag - tag or array of tags
	 * @return boolean
	 */
	public static function del_by_tags($tag)
	{
		return self::getMemoryObject()->del_by_tags($tag);
	}

	/**
	 * Select from storage by params
	 * Only values of 'array' type will be selected
	 * k - key, r - relation, v - value
	 * relations: "<", ">", "=" or "==", "!=" or "<>"
	 * example: select(array(array('k'=>'user_id',	'r'=>'<',	'v'=>1))); - select where user_id<1
	 * @param array $params
	 * @param bool $get_array
	 * @return mixed
	 */
	public static function select($params, $get_array = false)
	{
		return self::getMemoryObject()->select($params, $get_array);
	}

	/**
	 * Select from storage via callback function
	 * Only values of 'array' type will be selected
	 * @param callback $fx ($value_array,$key)
	 * @param bool $get_array
	 * @return mixed
	 */
	public static function select_fx($fx, $get_array = false)
	{
		return self::getMemoryObject()->select_fx($fx, $get_array);
	}

	/**
	 * Increment value of key
	 * @param string $key
	 * @param mixed $by_value
	 * if stored value is array:
	 *			 if $by_value is value in array, new element will be pushed to the end of array,
	 *			if $by_value is key=>value array, key=>value pair will be added (or updated)
	 * @param int $limit_keys_count - maximum count of elements (used only if stored value is array)
	 * @return int|string|array new value of key
	 */
	public static function increment($key, $by_value = 1, $limit_keys_count = 0)
	{
		return self::getMemoryObject()->increment($key, $by_value, $limit_keys_count);
	}

	public static function ini(IMemoryStorage $MemoryObject = NULL)
	{
		if (is_object($MemoryObject)) self::$memory_object = $MemoryObject;
	}

	/**
	 * Get exclusive mutex for key. Key will be still accessible to read and write, but
	 * another process can exclude dog-pile effect, if before updating the key he will try to get this mutex.
	 * Example:
	 * Process 1 reads key simultaneously with Process 2.
	 * Value of this key are too old, so Process 1 going to refresh it. Simultaneously with Process 2.
	 * But both of them trying to lock_key, and Process 1 only will refresh value of key (taking it from database, e.g.),
	 * and Process 2 can decide, what he want to do - use old value and not spent time to database, or something else.
	 * @static
	 * @param mixed $key
	 * @param mixed $auto_unlocker_variable - pass empty, just declared variable
	 */
	public static function lock_key($key, &$auto_unlocker_variable)
	{
		return self::getMemoryObject()->lock_key($key, $auto_unlocker_variable);
	}

	/**
	 * Unlock key, locked by method 'lock_key'
	 * @static
	 * @param Key_AutoUnlocker|NULL $auto_unlocker
	 * @return bool
	 */
	public static function unlock_key(Key_AutoUnlocker $auto_unlocker)
	{
		return self::getMemoryObject()->unlock_key($auto_unlocker);
	}

	public static function getLastErr()
	{
		return self::getMemoryObject()->getLastErr();
	}

	/** Return array of all stored keys */
	public static function get_keys()
	{
		return self::getMemoryObject()->get_keys();
	}

	/**
	 * @return array
	 */
	public static function get_stat()
	{
		return self::getMemoryObject()->get_stat();
	}

	public static function getErrLog()
	{
		return self::getMemoryObject()->getErrLog();
	}
}
