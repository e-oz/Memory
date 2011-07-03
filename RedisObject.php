<?php
namespace Jamm\Memory;

class RedisObject extends MemoryObject implements IMemoryStorage
{
	const lock_key_prefix = '.lock_key.';
	const tag_prefix = '.tag.';
	/** @var IRedisServer */
	protected $redis;
	protected $prefix;
	protected $tag_prefix;
	protected $lock_key_prefix;

	public function __construct($prefix = 'K', IRedisServer $RedisServer = NULL)
	{
		$this->prefix = str_replace('.', '_', $prefix).'.';
		if (!empty($RedisServer)) $this->redis = $RedisServer;
		else $this->setDefaultRedisServer();

		$this->tag_prefix = self::tag_prefix.$this->prefix;
		$this->lock_key_prefix = self::lock_key_prefix.$this->prefix;
	}

	protected function setDefaultRedisServer()
	{
		$this->redis = new RedisServer();
	}

	/**
	 * Add value to the memory storage, only if this key does not exists (or false will be returned).
	 *
	 * @param string $k
	 * @param mixed $v
	 * @param int $ttl
	 * @param array|string $tags
	 * @return boolean
	 */
	public function add($k, $v, $ttl = 259200, $tags = NULL)
	{
		$key = $this->prefix.$k;
		$set = $this->redis->SetNX($key, serialize($v));
		if (!$set) return false;
		$ttl = intval($ttl);
		if ($ttl < 1) $ttl = self::max_ttl;
		$this->redis->Expire($key, $ttl);
		if (!empty($tags)) $this->setTags($k, $tags);
		return true;
	}

	/**
	 * Set tags, associated with the key
	 *
	 * @param string $key
	 * @param string|array $tags
	 * @return bool
	 */
	public function setTags($key, $tags)
	{
		if (!is_array($tags)) $tags = array($tags);
		foreach ($tags as $tag)
		{
			if (!$this->redis->sAdd($this->tag_prefix.$tag, $key)) return false;
		}
		return true;
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
	public function save($k, $v, $ttl = 259200, $tags = NULL)
	{
		$ttl = intval($ttl);
		if ($ttl < 1) $ttl = self::max_ttl;
		$set = $this->redis->SetEX($this->prefix.$k, $ttl, serialize($v));
		if (!$set) return false;
		if (!empty($tags)) $this->setTags($k, $tags);
		return true;
	}

	/**
	 * Read data from memory storage
	 *
	 * @param string|array $k (string or array of string keys)
	 * @param mixed $ttl_left = (ttl - time()) of key. Use to exclude dog-pile effect, with lock/unlock_key methods.
	 * @return mixed
	 */
	public function read($k, &$ttl_left = -1)
	{
		$r = unserialize($this->redis->get($this->prefix.$k));
		if ($r===false) return false;
		if ($ttl_left!==-1)
		{
			$ttl_left = $this->redis->TTL($this->prefix.$k);
			if ($ttl_left < 1) $ttl_left = self::max_ttl;
		}
		return $r;
	}

	/**
	 * Delete key or array of keys from storage
	 * @param string|array $k - keys
	 * @return boolean|array - if array of keys was passed, on error will be returned array of not deleted keys, or 'true' on success.
	 */
	public function del($k)
	{
		if (!is_array($k)) $k = array($k);
		$todel = array();
		$tags = $this->redis->Keys($this->tag_prefix.'*');
		foreach ($k as $key)
		{
			$todel[] = $this->prefix.$key;
			foreach ($tags as $tag) $this->redis->sRem($tag, $key);
		}
		return $this->redis->Del($todel);
	}

	public function del_old()
	{
		//should be automatically done by Redis
		return true;
	}

	/**
	 * Delete keys by tags
	 *
	 * @param array|string $tag - tag or array of tags
	 * @return boolean
	 */
	public function del_by_tags($tag)
	{
		if (!is_array($tag)) $tag = array($tag);
		$d = 0;
		foreach ($tag as $t)
		{
			$keys = $this->redis->sMembers($this->tag_prefix.$t);
			if (!empty($keys)) $d += $this->del($keys);
			$this->redis->Del($this->tag_prefix.$t);
		}
		return $d;
	}

	/**
	 * Select from storage via callback function
	 * Only values of 'array' type will be selected
	 * @param callback $fx ($value_array,$key)
	 * @param bool $get_array
	 * @return mixed
	 */
	public function select_fx($fx, $get_array = false)
	{
		$arr = array();
		$l = strlen($this->prefix);
		$keys = $this->redis->Keys($this->prefix.'*');
		foreach ($keys as $key)
		{
			$s = unserialize($this->redis->Get($key));
			if (!is_array($s)) continue;
			$index = substr($key, $l);

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
	 * Increment value of key
	 * @param string $key
	 * @param mixed $by_value
	 * if stored value is array:
	 *			 if $by_value is value in array, new element will be pushed to the end of array,
	 *			if $by_value is key=>value array, key=>value pair will be added (or updated)
	 * @param int $limit_keys_count - maximum count of elements (used only if stored value is array)
	 * @return int|string|array new value of key
	 */
	public function increment($key, $by_value = 1, $limit_keys_count = 0)
	{
		if (empty($key))
		{
			$this->ReportError('empty keys are not allowed', __LINE__);
			return false;
		}

		if (!$this->acquire_key($key, $auto_unlocker)) return $this->ReportError('Can not acquire key', __LINE__);
		
		$value = $this->read($key);
		if ($value===null || $value===false) return $this->save($key, $by_value);

		if (is_numeric($value)) $value += $by_value;
		elseif (is_array($value))
		{
			if ($limit_keys_count > 0 && (count($value) > $limit_keys_count)) $value = array_slice($value, $limit_keys_count*(-1)+1);

			if (is_array($by_value))
			{
				$set_key = key($by_value);
				if (!empty($set_key)) $value[$set_key] = $by_value[$set_key];
				else $value[] = $by_value[0];
			}
			else $value[] = $by_value;
		}
		else $value .= $by_value;

		if ($this->save($key, $value)) return $value;
		else return false;
	}

	/**
	 * Get exclusive mutex for key. Key will be still accessible to read and write, but
	 * another process can exclude dog-pile effect, if before updating the key he will try to get this mutex.
	 * @param mixed $key
	 * @param mixed $auto_unlocker_variable - pass empty, just declared variable
	 */
	public function lock_key($key, &$auto_unlocker_variable)
	{
		$r = $this->redis->SetNX($this->lock_key_prefix.$key, 1);
		if (!$r) return false;
		$this->redis->Expire($this->lock_key_prefix.$key, self::key_lock_time);
		$auto_unlocker_variable = new KeyAutoUnlocker(array($this, 'unlock_key'));
		$auto_unlocker_variable->key = $key;
		return true;
	}

	/**
	 * Unlock key, locked by method 'lock_key'
	 * @param KeyAutoUnlocker $auto_unlocker
	 * @return bool
	 */
	public function unlock_key(KeyAutoUnlocker $auto_unlocker)
	{
		if (empty($auto_unlocker->key))
		{
			$this->ReportError('autoUnlocker should be passed', __LINE__);
			return false;
		}
		$auto_unlocker->revoke();
		return $this->redis->Del($this->lock_key_prefix.$auto_unlocker->key);
	}

	/**
	 * @return array
	 */
	public function get_stat()
	{
		return $this->redis->info();
	}

	/** Return array of all stored keys */
	public function get_keys()
	{
		$l = strlen($this->prefix);
		$keys = $this->redis->Keys($this->prefix.'*');
		foreach ($keys as &$key) $key = substr($key, $l);
		return $keys;
	}
}
