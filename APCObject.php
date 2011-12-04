<?php
namespace Jamm\Memory;

class APCObject extends MemoryObject implements IMemoryStorage
{
	protected $prefix = 'K'; //because I love my wife Katya :)
	protected $lock_key_prefix;
	protected $defragmentation_prefix;
	protected $tags_prefix;

	const lock_key_prefix        = '.lock_key.';
	const defragmentation_prefix = '.clean.';
	const tags_prefix            = '.tags.';
	const apc_arr_key            = 'key';
	const apc_arr_ctime          = 'creation_time';
	const apc_arr_ttl            = 'ttl';
	const apc_arr_value          = 'value';
	const apc_arr_atime          = 'access_time';

	/**
	 * @param string $ID
	 */
	public function __construct($ID = '')
	{
		$this->set_ID($ID);
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
	public function add($k, $v, $ttl = 259200, $tags = NULL)
	{
		if (empty($k))
		{
			$this->ReportError('empty keys are not allowed', __LINE__);
			return false;
		}

		$add = apc_add($this->prefix.$k, $v, intval($ttl));
		if (!$add)
		{
			if (!apc_exists($this->prefix.$k))
			{
				$this->ReportError('Can not add non existing key', __LINE__);
			}
			return false;
		}
		if (!empty($tags)) $this->set_tags($k, $tags, $ttl);
		return true;
	}

	/**
	 * Associate tags with keys
	 * @param string $key
	 * @param string|array $tags
	 * @param int $ttl
	 * @return bool
	 */
	public function set_tags($key, $tags, $ttl = self::max_ttl)
	{
		if (!is_array($tags))
		{
			if (is_scalar($tags)) $tags = array($tags);
			else $tags = array();
		}
		if (!empty($tags))
		{
			return apc_store($this->tags_prefix.$key, $tags, intval($ttl));
		}
		return false;
	}

	/**
	 * Save variable in memory storage
	 *
	 * @param string $k
	 * @param mixed $v
	 * @param int $ttl		   - time to live (store) in seconds
	 * @param array|string $tags - array of tags for this key
	 * @return bool
	 */
	public function save($k, $v, $ttl = 259200, $tags = NULL)
	{
		if (empty($k))
		{
			$this->ReportError('empty keys are not allowed', __LINE__);
			return false;
		}

		static $cleaned = false;
		if (!$cleaned)
		{
			$this->del_old_cached();
			$cleaned = true;
		}

		if (!apc_store($this->prefix.$k, $v, intval($ttl)))
		{
			$this->ReportError('apc can not store key', __LINE__);
			return false;
		}

		if (!empty($tags)) $this->set_tags($k, $tags, $ttl);
		return true;
	}

	/**
	 * Returns, how many seconds left till key expiring.
	 * @param string $key
	 * @return int
	 */
	public function getKeyTTL($key)
	{
		$i    = new \APCIterator('user', '/^'.preg_quote($this->prefix.$key).'$/', APC_ITER_TTL+APC_ITER_CTIME, 1);
		$item = $i->current();
		if (empty($item)) return NULL;
		if ($item[self::apc_arr_ttl]!=0) return (($item[self::apc_arr_ctime]+$item[self::apc_arr_ttl])-time());
		else return self::max_ttl;
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
		if (empty($k))
		{
			$this->ReportError('empty keys are not allowed', __LINE__);
			return NULL;
		}
		if (is_array($k))
		{
			$data       = array();
			$return_ttl = ($ttl_left!==-1 ? true : false);
			$ttl_left   = array();
			foreach ($k as $key)
			{
				$key        = (string)$key;
				$data[$key] = apc_fetch($this->prefix.$key, $success);
				if (!$success)
				{
					unset($data[$key]);
					continue;
				}
				if ($return_ttl) $ttl_left[$key] = $this->getKeyTTL($key);
			}
		}
		else
		{
			$data = apc_fetch($this->prefix.$k, $success);
			if (!$success)
			{
				if (apc_exists($this->prefix.$k))
				{
					$this->ReportError('apc can not fetch key '.$k, __LINE__);
				}
				return false;
			}
			if ($ttl_left!==-1)
			{
				$ttl_left = $this->getKeyTTL($k);
				if ($ttl_left < 0) $data = false; //key expired
			}
		}
		return $data;
	}

	/** Return array of all stored keys */
	public function get_keys()
	{
		$map = array();
		$l   = strlen($this->prefix);
		$i   = new \APCIterator('user', '/^'.preg_quote($this->prefix).'/', APC_ITER_KEY);
		foreach ($i as $item)
		{
			$map[] = substr($item[self::apc_arr_key], $l);
		}
		return $map;
	}

	/**
	 * Delete key or array of keys from storage
	 * @param string|array $k
	 * @return boolean
	 */
	public function del($k)
	{
		if (empty($k))
		{
			$this->ReportError('empty keys are not allowed', __LINE__);
			return false;
		}

		if (is_array($k))
		{
			$todel = array();
			foreach ($k as $key)
			{
				$todel[] = $this->prefix.$key;
				if (\apc_exists($this->tags_prefix.$key)) $todel[] = $this->tags_prefix.$key;
				if (\apc_exists($this->lock_key_prefix.$key)) $todel[] = $this->lock_key_prefix.$key;
			}
			$r = apc_delete($todel);
			if (empty($r)) return true;
			else return $r;
		}
		else
		{
			if (\apc_exists($this->tags_prefix.$k)) apc_delete($this->tags_prefix.$k);
			if (\apc_exists($this->lock_key_prefix.$k)) apc_delete($this->lock_key_prefix.$k);
			return apc_delete($this->prefix.$k);
		}
	}

	/**
	 * Delete old (by ttl) variables from storage
	 * It's very important function to prevent APC's cache fragmentation.
	 * @return boolean
	 */
	public function del_old()
	{
		$t             = time();
		$todel         = array();
		$apc_user_info = apc_cache_info('user', true);
		$apc_ttl       = 0;
		if (!empty($apc_user_info['ttl']))
		{
			$apc_ttl = $apc_user_info['ttl']/2;
		}

		$i = new \APCIterator('user', null, APC_ITER_TTL+APC_ITER_KEY+APC_ITER_CTIME+APC_ITER_ATIME);
		foreach ($i as $key)
		{
			if ($key[self::apc_arr_ttl] > 0 && ($t-$key[self::apc_arr_ctime]) > $key[self::apc_arr_ttl]) $todel[] = $key[self::apc_arr_key];
			else
			{
				//this code is necessary to prevent deletion variables from cache by apc.ttl (they deletes not by their ttl+ctime, but apc.ttl+atime)
				if ($apc_ttl > 0 && (($t-$key[self::apc_arr_atime]) > $apc_ttl)) apc_fetch($key[self::apc_arr_key]);
			}
		}
		if (!empty($todel))
		{
			$r = apc_delete($todel);
			if (!empty($r)) return $r;
			else return true;
		}
		return true;
	}

	protected function del_old_cached()
	{
		$t             = time();
		$apc_user_info = apc_cache_info('user', true);
		if (!empty($apc_user_info['ttl']))
		{
			$apc_ttl      = $apc_user_info['ttl']/2;
			$check_period = $apc_ttl;
		}
		if (empty($check_period) || $check_period > 1800) $check_period = 1800;

		$ittl              = new \APCIterator('user', '/^'.preg_quote($this->defragmentation_prefix).'$/', APC_ITER_ATIME, 1);
		$cttl              = $ittl->current();
		$previous_cleaning = $cttl[self::apc_arr_atime];
		if (empty($previous_cleaning) || ($t-$previous_cleaning) > $check_period)
		{
			apc_store($this->defragmentation_prefix, $t, $check_period);
			$this->del_old();
		}
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

		$todel = array();
		$l     = strlen($this->tags_prefix);
		$i     = new \APCIterator('user', '/^'.preg_quote($this->tags_prefix).'/', APC_ITER_KEY+APC_ITER_VALUE);
		foreach ($i as $key_tags)
		{
			if (is_array($key_tags[self::apc_arr_value]))
			{
				$intersect = array_intersect($tags, $key_tags[self::apc_arr_value]);
				if (!empty($intersect)) $todel[] = substr($key_tags[self::apc_arr_key], $l);
			}
		}

		if (!empty($todel)) return $this->del($todel);
		return true;
	}

	/**
	 * Select from storage via callback function
	 *
	 * @param callback $fx ($value_array,$key)
	 * @param bool $get_array
	 * @return mixed
	 */
	public function select_fx($fx, $get_array = false)
	{
		$arr = array();
		$l   = strlen($this->prefix);
		$i   = new \APCIterator('user', '/^'.preg_quote($this->prefix).'/', APC_ITER_KEY+APC_ITER_VALUE);
		foreach ($i as $item)
		{
			if (!is_array($item[self::apc_arr_value])) continue;
			$s     = $item[self::apc_arr_value];
			$index = substr($item[self::apc_arr_key], $l);

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

		$value = apc_fetch($this->prefix.$key, $success);
		if (!$success)
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
			$value = $value+$by_value;
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
	 * Example:
	 * Process 1 reads key simultaneously with Process 2.
	 * Value of this key are too old, so Process 1 going to refresh it. Simultaneously with Process 2.
	 * But both of them trying to lock_key, and Process 1 only will refresh value of key (taking it from database, e.g.),
	 * and Process 2 can decide, what he want to do - use old value and not spent time to database, or something else.
	 * @param mixed $key
	 * @param mixed $auto_unlocker_variable - pass empty, just declared variable
	 * @return bool
	 */
	public function lock_key($key, &$auto_unlocker_variable)
	{
		$r = apc_add($this->lock_key_prefix.$key, 1, $this->key_lock_time);
		if (!$r) return false;
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
			$this->ReportError('Empty name of key in the AutoUnlocker', __LINE__);
			return false;
		}
		$auto_unlocker->revoke();
		return apc_delete($this->lock_key_prefix.$key);
	}

	/**
	 * @return array
	 */
	public function get_stat()
	{
		return array(
			'system' => apc_cache_info('', true),
			'user' => apc_cache_info('user', true)
		);
	}

	public function set_ID($ID)
	{
		if (!empty($ID))
		{
			$this->prefix = str_replace('.', '_', $ID).'.';
		}
		$this->lock_key_prefix        = self::lock_key_prefix.$this->prefix;
		$this->defragmentation_prefix = self::defragmentation_prefix;
		$this->tags_prefix            = self::tags_prefix.$this->prefix;
	}

	public function get_ID()
	{
		return str_replace('_', '.', trim($this->prefix, '.'));
	}
}
