<?php
namespace Jamm\Memory;

class RedisObject extends MemoryObject implements IMemoryStorage
{
	const lock_key_prefix = '.lock_key.';
	const tag_prefix      = '.tag.';
	/** @var IRedisServer */
	protected $redis;
	protected $prefix = 'K';
	protected $tag_prefix;
	protected $lock_key_prefix;

	public function __construct($ID = '', IRedisServer $RedisServer = NULL)
	{
		if (!empty($RedisServer)) $this->redis = $RedisServer;
		else $this->setDefaultRedisServer();
		$this->set_ID($ID);
	}

	protected function setDefaultRedisServer()
	{
		$this->redis = new RedisServer();
	}

	/**
	 * Add value to the memory storage, only if this key does not exists (or false will be returned).
	 *
	 * @param string $key
	 * @param mixed $value
	 * @param int $ttl
	 * @param array|string $tags
	 * @return boolean
	 */
	public function add($key, $value, $ttl = 259200, $tags = NULL)
	{
		$redis_key = $this->prefix.$key;
		$set       = $this->redis->SetNX($redis_key, serialize($value));
		if (!$set) return false;
		$ttl = intval($ttl);
		if ($ttl < 1) $ttl = self::max_ttl;
		$this->redis->Expire($redis_key, $ttl);
		if (!empty($tags)) $this->setTags($key, $tags);
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
	 * @param string $key			- key
	 * @param mixed $value		   - value
	 * @param int $ttl			   - time to live (store) in seconds
	 * @param array|string $tags	 - array of tags for this key
	 * @return bool
	 */
	public function save($key, $value, $ttl = 259200, $tags = NULL)
	{
		$ttl = intval($ttl);
		if ($ttl < 1) $ttl = self::max_ttl;
		$set = $this->redis->SetEX($this->prefix.$key, $ttl, serialize($value));
		if (!$set) return false;
		if (!empty($tags)) $this->setTags($key, $tags);
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
		$r = unserialize($this->redis->get($this->prefix.$key));
		if ($r===false) return false;
		if ($ttl_left!==-1)
		{
			$ttl_left = $this->redis->TTL($this->prefix.$key);
			if ($ttl_left < 1) $ttl_left = self::max_ttl;
		}
		return $r;
	}

	/**
	 * Delete key or array of keys from storage
	 * @param string|array $keys - keys
	 * @return boolean|array - if array of keys was passed, on error will be returned array of not deleted keys, or 'true' on success.
	 */
	public function del($keys)
	{
		if (empty($keys)) return false;
		if (!is_array($keys)) $keys = array($keys);
		$todel = array();
		$tags  = $this->redis->Keys($this->tag_prefix.'*');
		foreach ($keys as $key)
		{
			$todel[] = $this->prefix.$key;
			if (!empty($tags))
			{
				foreach ($tags as $tag) $this->redis->sRem($tag, $key);
			}
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
	 * @param array|string $tags - tag or array of tags
	 * @return boolean
	 */
	public function del_by_tags($tags)
	{
		if (!is_array($tags)) $tags = array($tags);
		$d = 0;
		foreach ($tags as $tag)
		{
			$keys = $this->redis->sMembers($this->tag_prefix.$tag);
			if (!empty($keys)) $d += $this->del($keys);
			$this->redis->Del($this->tag_prefix.$tag);
		}
		return $d;
	}

	/**
	 * Select from storage via callback function
	 * @param callback $fx ($value, $key) - should return true to select key(s)
	 * @param bool $get_array
	 * @return mixed
	 */
	public function select_fx($fx, $get_array = false)
	{
		$arr           = array();
		$prefix_length = strlen($this->prefix);
		$keys          = $this->redis->Keys($this->prefix.'*');
		foreach ($keys as $key)
		{
			$content = $this->redis->Get($key);
			if (empty($content)) continue;
			$value = unserialize($content);
			$index = substr($key, $prefix_length);
			if ($fx($value, $index)===true)
			{
				if (!$get_array) return $value;
				else $arr[$index] = $value;
			}
		}
		if (!$get_array || empty($arr)) return false;
		else return $arr;
	}

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
	public function increment($key, $by_value = 1, $limit_keys_count = 0, $ttl = 259200)
	{
		if (empty($key))
		{
			$this->ReportError('empty keys are not allowed', __LINE__);
			return false;
		}

		if (!$this->acquire_key($key, $auto_unlocker)) return false;

		$value = $this->read($key);
		if ($value===null || $value===false) return $this->save($key, $by_value, $ttl);

		if (is_array($value))
		{
			$value = $this->incrementArray($limit_keys_count, $value, $by_value);
		}
		elseif (is_numeric($value) && is_numeric($by_value))
		{
			$value += $by_value;
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
	 */
	public function lock_key($key, &$auto_unlocker_variable)
	{
		$r = $this->redis->SetNX($this->lock_key_prefix.$key, 1);
		if (!$r) return false;
		$this->redis->Expire($this->lock_key_prefix.$key, $this->key_lock_time);
		$auto_unlocker_variable = new KeyAutoUnlocker(array($this, 'unlock_key'));
		$auto_unlocker_variable->setKey($key);
		return true;
	}

	/**
	 * Unlock key, locked by method 'lock_key'
	 * @param KeyAutoUnlocker $auto_unlocker
	 * @return bool
	 */
	public function unlock_key(KeyAutoUnlocker $auto_unlocker)
	{
		$key = $auto_unlocker->getKey();
		if (empty($key))
		{
			$this->ReportError('Empty key in the AutoUnlocker', __LINE__);
			return false;
		}
		$auto_unlocker->revoke();
		return $this->redis->Del($this->lock_key_prefix.$key);
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
		$l    = strlen($this->prefix);
		$keys = $this->redis->Keys($this->prefix.'*');
		if (!empty($keys))
		{
			foreach ($keys as &$key) $key = substr($key, $l);
			return $keys;
		}
		else return array();
	}

	public function set_ID($ID)
	{
		if (!empty($ID))
		{
			$this->prefix = str_replace('.', '_', $ID).'.';
		}
		$this->tag_prefix      = self::tag_prefix.$this->prefix;
		$this->lock_key_prefix = self::lock_key_prefix.$this->prefix;
	}

	public function get_ID()
	{
		return str_replace('_', '.', trim($this->prefix, '.'));
	}
}
