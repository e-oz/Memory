<?php
namespace Jamm\Memory;
class RedisObject extends MemoryObject implements IMemoryStorage
{
	const lock_key_prefix   = '.lock_key.';
	const tag_prefix        = '.tag.';
	const serialized_prefix = '.s_key.';
	/** @var IRedisServer */
	protected $redis;
	protected $prefix = 'K';
	protected $tag_prefix;
	protected $lock_key_prefix;
	protected $serialize_key_prefix;

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
		$ttl       = intval($ttl);
		if (!is_scalar($value))
		{
			$set = $this->redis->SetNX($redis_key, serialize($value));
			if (!$set) return false;
			$this->setKeySerialization(true, $key, $ttl);
		}
		else
		{
			$set = $this->redis->SetNX($redis_key, $value);
			if (!$set) return false;
			$this->setKeySerialization(false, $key, $ttl);
		}
		if ($ttl > 0)
		{
			$this->redis->Expire($redis_key, $ttl);
		}
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
	 * @param string $key            - key
	 * @param mixed $value           - value
	 * @param int $ttl               - time to live (store) in seconds
	 * @param array|string $tags     - array of tags for this key
	 * @return bool
	 */
	public function save($key, $value, $ttl = 259200, $tags = NULL)
	{
		$ttl = intval($ttl);
		if ($ttl > 0)
		{
			if (is_scalar($value))
			{
				$set = $this->redis->SetEX($this->prefix.$key, $ttl, $value);
				$this->setKeySerialization(false, $key, $ttl);
			}
			else
			{
				$set = $this->redis->SetEX($this->prefix.$key, $ttl, serialize($value));
				$this->setKeySerialization(true, $key, $ttl);
			}
		}
		else
		{
			if (is_scalar($value))
			{
				$set = $this->redis->Set($this->prefix.$key, $value);
				$this->setKeySerialization(false, $key, 0);
			}
			else
			{
				$set = $this->redis->Set($this->prefix.$key, serialize($value));
				$this->setKeySerialization(true, $key, $ttl);
			}
		}
		if (!$set) return false;
		if (!empty($tags)) $this->setTags($key, $tags);
		return true;
	}

	protected function setKeySerialization($is_serialized, $key, $ttl)
	{
		if (!$is_serialized)
		{
			$this->redis->Del($this->serialize_key_prefix.$key);
		}
		else
		{
			if ($ttl > 0)
			{
				$this->redis->SetEX($this->serialize_key_prefix.$key, $ttl, 1);
			}
			else
			{
				$this->redis->Set($this->serialize_key_prefix.$key, 1);
			}
		}
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
				$arr_key = (string)$arr_key;
				if (isset($data[$arr_key]) && ($data[$arr_key]===false || $data[$arr_key]===null))
				{
					unset($data[$arr_key]);
					continue;
				}
				if ($return_ttl)
				{
					$data[$arr_key]     = $this->read_value($arr_key, $arr_key_ttl_left);
					$ttl_left[$arr_key] = $arr_key_ttl_left;
				}
				else
				{
					$data[$arr_key] = $this->read_value($arr_key);
				}
			}
			return $data;
		}
		else
		{
			return $this->read_value($key, $ttl_left);
		}
	}

	/**
	 * @param $key
	 * @param $ttl_left
	 * @return mixed|string
	 */
	protected function read_value($key, &$ttl_left = -1)
	{
		$value = $this->redis->get($this->prefix.$key);
		if ($this->redis->Exists($this->serialize_key_prefix.$key))
		{
			$value = unserialize($value);
		}
		if ($ttl_left!==-1)
		{
			$ttl_left = $this->redis->TTL($this->prefix.$key);
			if ($ttl_left < 0) $ttl_left = 0;
		}
		return $value;
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
			$todel[] = $this->serialize_key_prefix.$key;
			$todel[] = $this->lock_key_prefix.$key;
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
	 * @param callable $fx ($value, $key) - should return true to select key(s)
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
			$index = substr($key, $prefix_length);
			$value = $this->read($index);
			if (empty($value)) continue;
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
	 *                              if stored value is an array:
	 *                              if $by_value is a value in array, new element will be pushed to the end of array,
	 *                              if $by_value is a key=>value array, new key=>value pair will be added (or updated)
	 * @param int $limit_keys_count - maximum count of elements (used only if stored value is array)
	 * @param int $ttl              - set time to live for key
	 * @return int|string|array new value of key
	 */
	public function increment($key, $by_value = 1, $limit_keys_count = 0, $ttl = 259200)
	{
		if (empty($key))
		{
			$this->ReportError('empty keys are not allowed', __LINE__);
			return false;
		}
		if (is_numeric($by_value) && !$this->redis->Exists($this->serialize_key_prefix.$key))
		{
			if (!($key_exists = $this->redis->Exists($this->prefix.$key)) || is_numeric($this->redis->Get($this->prefix.$key)))
			{
				if ($by_value >= 0)
				{
					$result = $this->redis->IncrBy($this->prefix.$key, $by_value);
				}
				else
				{
					$result = $this->redis->DecrBy($this->prefix.$key, $by_value*(-1));
				}
				if ($result!==false)
				{
					if ($ttl > 0)
					{
						$this->redis->Expire($this->prefix.$key, $ttl);
					}
					return $result;
				}
			}
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
	 * @return bool
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
		$this->tag_prefix           = self::tag_prefix.$this->prefix;
		$this->lock_key_prefix      = self::lock_key_prefix.$this->prefix;
		$this->serialize_key_prefix = self::serialized_prefix.$this->prefix;
	}

	public function get_ID()
	{
		return str_replace('_', '.', trim($this->prefix, '.'));
	}
}
