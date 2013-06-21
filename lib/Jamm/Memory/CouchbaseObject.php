<?php
namespace Jamm\Memory;
class CouchbaseObject extends MemoryObject implements IMemoryStorage
{
	protected $prefix = 'K'; //because I love my wife Katya :)
	/** @var string */
	protected $lock_key_prefix;
	/** @var string $ttl_table_name array (key=>ttl) */
	protected $ttl_table_name;
	protected $tag_prefix;
	/** @var \Couchbase */
	protected $Couchbase;

	const lock_key_prefix  = '.lock_key.';
	const ttl_table_prefix = '.ttl.';
	const tag_prefix       = '.tags.';

	public function __construct(\Couchbase $Couchbase, $ID = '')
	{
		$this->Couchbase = $Couchbase;
		$this->set_ID($ID);
	}

	protected function setCouchbase(\Couchbase $Couchbase)
	{
		$this->Couchbase = $Couchbase;
	}

	/**
	 * Add value to memory storage, only if this key does not exists (or false will be returned).
	 *
	 * @param string $key
	 * @param mixed $value
	 * @param int $ttl
	 * @param array|string $tags
	 * @return boolean
	 */
	public function add($key, $value, $ttl = 259200, $tags = NULL)
	{
		$ttl = $this->ttl_to_expiration($ttl);
		try
		{
			$result = $this->Couchbase->add($this->prefix.$key, $value, $ttl);
		}
		catch (\Exception $Exception)
		{
			return false;
		}
		if (!$result)
		{
			return false;
		}
		$this->setKeyTTL($key, $ttl);
		if (!empty($tags)) $this->setTags($key, $tags);
		return true;
	}

	protected function setKeyTTL($key, $ttl)
	{
		try
		{
			if ($this->acquire_key($this->ttl_table_name, $AutoUnlocker))
			{
				$ttl_table       = $this->Couchbase->get($this->ttl_table_name);
				$ttl_table[$key] = $ttl;
				$this->Couchbase->set($this->ttl_table_name, $ttl_table);
				$this->unlock_key($AutoUnlocker);
			}
		}
		catch (\Exception $Exception)
		{
			$this->ReportError('Can not set ttl for key', __LINE__);
			return false;
		}
	}

	protected function ttl_to_expiration($ttl)
	{
		$ttl = intval($ttl);
		if ($ttl <= 0) return 0;
		return $ttl+time();
	}

	protected function &read_TTL_table()
	{
		$ttl_table = $this->Couchbase->get($this->ttl_table_name);
		if (empty($ttl_table)) $ttl_table = array();
		return $ttl_table;
	}

	/**
	 * Delete key or array of keys from storage
	 * @param string|array $key - keys
	 * @return boolean|array - if array of keys was passed, on error will be returned array of not deleted keys, or 'true' on success.
	 */
	public function del($key)
	{
		$this->acquire_key($this->ttl_table_name, $auto_unlocker);
		$ttl_table         = $this->read_TTL_table();
		$ttl_table_changed = false;
		$deleted           = false;
		if (!is_array($key)) $key = array($key);
		foreach ($key as $k)
		{
			$k       = (string)$k;
			$deleted = $this->Couchbase->delete($this->prefix.$k);
			$this->Couchbase->delete($this->lock_key_prefix.$k);
			if ($this->delTTLOfKey($k, $ttl_table)) $ttl_table_changed = true;
		}
		if ($ttl_table_changed) $this->save_TTL_table($ttl_table);
		return $deleted;
	}

	protected function delTTLOfKey($key, &$ttl_table)
	{
		if (array_key_exists($key, $ttl_table))
		{
			unset($ttl_table[$key]);
			return true;
		}
		return false;
	}

	/**
	 * Delete keys by tags
	 *
	 * @param array|string $tags - tag or array of tags
	 * @return boolean
	 */
	public function del_by_tags($tags)
	{
		if (!is_array($tags)) $tags = array($tags);
		$d = 0;
		foreach ($tags as $tag)
		{
			$keys = $this->getKeysOfTag($tag);
			if (!empty($keys)) $d += $this->del($keys);
			$this->Couchbase->delete($this->tag_prefix.$tag);
		}
		return $d;
	}

	/**
	 * Delete old (by ttl) variables from storage
	 * @return boolean
	 */
	public function del_old()
	{
		$this->acquire_key($this->ttl_table_name, $auto_unlocker);
		$ttl_table = $this->read_TTL_table();
		if (empty($ttl_table))
		{
			return true;
		}
		$t       = time();
		$changed = false;
		foreach ($ttl_table as $key => $ttl)
		{
			if ($ttl!==0 && $ttl < $t)
			{
				$this->Couchbase->delete($this->prefix.$key);
				unset($ttl_table[$key]);
				$changed = true;
			}
		}
		if ($changed)
		{
			$this->Couchbase->set($this->ttl_table_name, $ttl_table, 0);
		}
		return true;
	}

	/** Return array of all stored keys */
	public function get_keys()
	{
		return array_keys($this->read_TTL_table());
	}

	/**
	 * Increment value of key
	 * @param string $key
	 * @param mixed $by_value
	 *                              if stored value is array:
	 *                              if $by_value is value in array, new element will be pushed to the end of array,
	 *                              if $by_value is key=>value array, key=>value pair will be added (or updated)
	 * @param int $limit_keys_count - maximum count of elements (used only if stored value is array)
	 * @param int $ttl
	 * @return int|string|array new value of key
	 */
	public function increment($key, $by_value = 1, $limit_keys_count = 0, $ttl = 259200)
	{
		if (!$this->acquire_key($key, $auto_unlocker)) return false;
		$value = $this->Couchbase->get($this->prefix.$key);
		if (empty($value))
		{
			if ($this->save($key, $by_value, $ttl)) return $by_value;
			else return false;
		}
		$expiration = $this->ttl_to_expiration($ttl);
		if (is_array($value))
		{
			$value = $this->incrementArray($limit_keys_count, $value, $by_value);
		}
		elseif (is_numeric($value) && is_numeric($by_value))
		{
			if ($by_value > 0)
			{
				$this->setKeyTTL($key, $expiration);
				return $this->Couchbase->increment($this->prefix.$key, $by_value, true, $expiration);
			}
			else
			{
				$this->setKeyTTL($key, $expiration);
				return $this->Couchbase->decrement($this->prefix.$key, $by_value*-1, true, $expiration);
			}
		}
		else
		{
			$this->setKeyTTL($key, $expiration);
			$this->Couchbase->append($this->prefix.$key, $by_value, $expiration);
			return $this->Couchbase->get($this->prefix.$key);
		}
		if ($this->save($key, $value, $ttl)) return $value;
		else return false;
	}

	/**
	 * Get exclusive mutex for key. Key will be still accessible to read and write, but
	 * another process can exclude dog-pile effect, if before updating the key he will try to get this mutex.
	 * @param mixed $key
	 * @param mixed $auto_unlocker_variable - pass empty, just declared variable
	 * @return bool
	 */
	public function lock_key($key, &$auto_unlocker_variable)
	{
		$r = $this->Couchbase->add($this->lock_key_prefix.$key, 1, $this->key_lock_time);
		if (!$r) return false;
		$auto_unlocker_variable = new KeyAutoUnlocker(array($this, 'unlock_key'));
		$auto_unlocker_variable->setKey($key);
		return true;
	}

	/**
	 * Read data from memory storage
	 *
	 * @param string|array $key (string or array of string keys)
	 * @param mixed $ttl_left   = (ttl - time()) of key. Use to exclude dog-pile effect, with lock/unlock_key methods.
	 * @return mixed
	 */
	public function read($key, &$ttl_left = -1)
	{
		if (is_array($key))
		{
			$data       = array();
			$return_ttl = ($ttl_left!==-1 ? true : false);
			$ttl_left   = array();
			foreach ($key as $arr_key)
			{
				$arr_key        = (string)$arr_key;
				$data[$arr_key] = $this->Couchbase->get($this->prefix.$arr_key);
				if ($data[$arr_key]===false || $data[$arr_key]===null)
				{
					unset($data[$arr_key]);
					continue;
				}
				if ($return_ttl)
				{
					$ttl_left[$arr_key] = $this->getKeyTTL($arr_key);
					if ($ttl_left < 0)
					{
						unset($data[$arr_key]);
						$this->del($arr_key);
					}
				}
			}
		}
		else
		{
			$data = $this->Couchbase->get($this->prefix.$key);
			if ($data===false || $data===null)
			{
				if (strlen($key) > 250) $this->ReportError('Length of key should be <250', __LINE__);
				return false;
			}
			if ($ttl_left!==-1)
			{
				$ttl_left = $this->getKeyTTL($key);
				if ($ttl_left < 0) //key expired
				{
					$data = false;
					$this->del($key);
				}
			}
		}
		return $data;
	}

	/**
	 * Save variable in memory storage
	 *
	 * @param string $key            - key
	 * @param mixed $value           - value
	 * @param int $ttl               - time to live (store) in seconds
	 * @param array|string $tags     - array of tags for this key
	 * @return bool
	 */
	public function save($key, $value, $ttl = 259200, $tags = NULL)
	{
		$key = (string)$key;
		$ttl = $this->ttl_to_expiration($ttl);
		try
		{
			if (false===$this->Couchbase->set($this->prefix.$key, $value, $ttl))
			{
				$reason = $this->prefix.$key;
				if (strlen($key) > 250) $reason = 'key length should be <250';
				$this->ReportError('Couchbase can not store key: '.$reason, __LINE__);
				return false;
			}
			$this->setKeyTTL($key, $ttl);
			if (!empty($tags)) $this->setTags($key, $tags);
			return true;
		}
		catch (\Exception $Exception)
		{
			return false;
		}
	}

	/**
	 * Select from storage via callback function
	 * Only values of 'array' type will be selected
	 * @param callable $fx ($value_array,$key)
	 * @param bool $get_array
	 * @return mixed
	 */
	public function select_fx($fx, $get_array = false)
	{
		$arr  = array();
		$keys = $this->get_keys();
		if (empty($keys)) return false;
		foreach ($keys as $index)
		{
			$s = $this->read($index);
			if (!is_array($s)) continue;
			if ($fx($s, $index)===true)
			{
				if (!$get_array) return $s;
				else $arr[$index] = $s;
			}
		}
		if (!$get_array || empty($arr)) return false;
		else return $arr;
	}

	/**
	 * Unlock key, locked by method 'lock_key'
	 * @param KeyAutoUnlocker $auto_unlocker
	 * @return bool
	 */
	public function unlock_key(KeyAutoUnlocker $auto_unlocker)
	{
		$key = $auto_unlocker->getKey();
		$auto_unlocker->revoke();
		return $this->Couchbase->delete($this->lock_key_prefix.$key);
	}

	/**
	 * @param string $key
	 * @param array|string $tags
	 * @return bool
	 */
	protected function setTags($key, $tags)
	{
		if (!is_array($tags)) $tags = array($tags);
		foreach ($tags as $tag)
		{
			try
			{
				if (!$this->Couchbase->add($this->tag_prefix.$tag, '"'.addcslashes($key, '\\').'"'))
				{
					$this->Couchbase->append($this->tag_prefix.$tag, ',"'.addcslashes($key, '\\').'"');
				}
			}
			catch (\Exception $Exception)
			{
				$this->ReportError('Can\'t set tag '.$tag.' for key '.$key, __LINE__);
			}
		}
		return true;
	}

	protected function getKeysOfTag($tag)
	{
		$data = $this->Couchbase->get($this->tag_prefix.$tag);
		if (empty($data))
		{
			return false;
		}
		$keys = json_decode('['.ltrim($data, ',').']');
		return $keys;
	}

	public function getKeyTTL($key)
	{
		$ttl_table = $this->read_TTL_table();
		$key       = (string)$key;
		if (!array_key_exists($key, $ttl_table)) return false;
		else
		{
			if ($ttl_table[$key]===0) return 0;
			return ($ttl_table[$key]-time());
		}
	}

	/**
	 * @param array $table
	 * @return void
	 */
	protected function save_TTL_table($table)
	{
		if (!empty($table))
		{
			$t = time();
			foreach ($table as $key => $ttl)
			{
				if ($ttl!==0 && $ttl < $t) unset($table[$key]);
			}
		}
		try
		{
			$this->Couchbase->set($this->ttl_table_name, $table, 0);
		}
		catch (\Exception $Exception)
		{
			$this->ReportError('Couchbase can not save ttl table', __LINE__);
		}
	}

	/**
	 * @return array
	 */
	public function get_stat()
	{
		return $this->Couchbase->getStats();
	}

	public function set_ID($ID)
	{
		if (!empty($ID))
		{
			$this->prefix = str_replace('.', '_', $ID).'.';
		}
		$this->lock_key_prefix = self::lock_key_prefix.$this->prefix;
		$this->ttl_table_name  = self::ttl_table_prefix.$this->prefix;
		$this->tag_prefix      = self::tag_prefix.$this->prefix;
	}

	public function get_ID()
	{
		return str_replace('_', '.', trim($this->prefix, '.'));
	}
}
