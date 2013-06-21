<?php
namespace Jamm\Memory;
class MemcacheObject extends MemoryObject implements IMemoryStorage
{
	protected $prefix = 'K'; //because I love my wife Katya :)
	/** @var string */
	protected $lock_key_prefix;
	/** @var string $ttl_table_name array (key=>ttl) */
	protected $ttl_table_name;
	protected $tag_prefix;
	/** @var IMemcacheDecorator */
	protected $memcache;

	const lock_key_prefix  = '.lock_key.';
	const ttl_table_prefix = '.ttl.';
	const tag_prefix       = '.tags.';

	/**
	 * @param string $ID Symbol "." will be replaced to "_"
	 * @param string $host
	 * @param int $port
	 */
	public function __construct($ID = '', $host = 'localhost', $port = 11211)
	{
		$this->setMemcacheObject($host, $port);
		$this->set_ID($ID);
	}

	protected function setMemcacheObject($host = 'localhost', $port = 11211)
	{
		$this->memcache = new \Memcache();
		if (!$this->memcache->connect($host, $port))
		{
			$this->ReportError('memcache connection error', __LINE__);
		}
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
		if (!$this->memcache->add($this->prefix.$key, $value, null, $ttl))
		{
			return false;
		}
		$this->setKeyTTL($key, $ttl);
		if (!empty($tags)) $this->setTags($key, $tags);
		return true;
	}

	protected function setKeyTTL($key, $ttl)
	{
		if ($this->acquire_key($this->ttl_table_name, $AutoUnlocker))
		{
			$ttl_table       = $this->memcache->get($this->ttl_table_name);
			$ttl_table[$key] = $ttl;
			$this->memcache->set($this->ttl_table_name, $ttl_table);
			$this->unlock_key($AutoUnlocker);
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
		$ttl_table = $this->memcache->get($this->ttl_table_name);
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
			$deleted = $this->memcache->delete($this->prefix.$k);
			$this->memcache->delete($this->lock_key_prefix.$k);
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
			$this->memcache->delete($this->tag_prefix.$tag);
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
		if (empty($ttl_table)) return true;
		$t       = time();
		$changed = false;
		foreach ($ttl_table as $key => $ttl)
		{
			if ($ttl!==0 && $ttl < $t)
			{
				$this->memcache->delete($this->prefix.$key);
				unset($ttl_table[$key]);
				$changed = true;
			}
		}
		if ($changed) $this->memcache->set($this->ttl_table_name, $ttl_table, null, 0);
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
		$this->setKeyTTL($key, $this->ttl_to_expiration($ttl));
		$value = $this->memcache->get($this->prefix.$key);
		if (empty($value))
		{
			if ($this->save($key, $by_value, $ttl)) return $by_value;
			else return false;
		}
		if (is_array($value))
		{
			$value = $this->incrementArray($limit_keys_count, $value, $by_value);
		}
		elseif (is_numeric($value) && is_numeric($by_value))
		{
			if ($by_value > 0)
			{
				return $this->memcache->increment($this->prefix.$key, $by_value);
			}
			else
			{
				return $this->memcache->decrement($this->prefix.$key, $by_value*-1);
			}
		}
		else
		{
			$value .= $by_value;
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
		$r = $this->memcache->add($this->lock_key_prefix.$key, 1, null, $this->key_lock_time);
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
				$data[$arr_key] = $this->memcache->get($this->prefix.$arr_key);
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
			$data = $this->memcache->get($this->prefix.$key);
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
		if (false===$this->memcache->set($this->prefix.$key, $value, 0, $ttl))
		{
			$reason = $this->prefix.$key;
			if (strlen($key) > 250) $reason = 'key length should be <250';
			elseif (strlen(serialize($value)) > 1048576) $reason = 'size of value should be <1Mb';
			$this->ReportError('memcache can not store key: '.$reason, __LINE__);
			return false;
		}
		$this->setKeyTTL($key, $ttl);
		if (!empty($tags)) $this->setTags($key, $tags);
		return true;
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
		return $this->memcache->delete($this->lock_key_prefix.$key);
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
			$id = $this->tag_prefix.$tag;
			if ($this->acquire_key($id, $AutoUnlocker))
			{
				$keys   = $this->memcache->get($id);
				$keys[] = $key;
				if (!$this->memcache->set($id, array_unique($keys)))
				{
					$this->ReportError('Can\'t set tag '.$tag.' for key '.$key, __LINE__);
				}

			}
		}
		return true;
	}

	protected function getKeysOfTag($tag)
	{
		return $this->memcache->get($this->tag_prefix.$tag);
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
		if (false===$this->memcache->set($this->ttl_table_name, $table, null, 0))
		{
			$this->ReportError('memcache can not save ttl table', __LINE__);
		}
	}

	/**
	 * @return array
	 */
	public function get_stat()
	{
		return $this->memcache->getStats();
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
