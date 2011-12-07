<?php
namespace Jamm\Memory\Shm;

abstract class SingleMemory extends \Jamm\Memory\MemoryObject implements ISingleMemory
{
	/** @var IMutex $sem */
	protected $sem;
	protected $mem = array();

	const map_info = 'info';
	const map_info_resized = 'resized';
	const map_info_resizetime = 'resized_lasttime';
	const map_keys = 'keys';
	const map_key_ttl = 'ttl';
	const map_key_tags = 'tags';
	const map_key_locks = 'locks';
	const map_key_cleantime = 'clean';

	/**
	 * @param string $k key
	 * @param mixed $v  value
	 * @param int $ttl  time to live
	 * @param string|array $tags
	 * @return bool|void
	 */
	public function save($k, $v, $ttl = 2592000, $tags = NULL)
	{
		if (empty($k) || $v===NULL)
		{
			$this->ReportError('empty keys and null values are not allowed', __LINE__);
			return false;
		}
		$k = (string)$k;
		$auto_unlocker = NULL;
		if (!$this->sem->get_access_write($auto_unlocker))
		{
			return false;
		}

		$this->del_old();

		$this->readmemory();
		$this->mem[self::map_keys][$k] = $v;
		$ttl = intval($ttl);
		if ($ttl > 0) $this->mem[self::map_key_ttl][$k] = time()+$ttl;
		if (!empty($tags))
		{
			if (!is_array($tags)) $tags = array($tags);
			foreach ($tags as $tag)
			{
				if (empty($this->mem[self::map_key_tags][$tag])
						|| !in_array($k, $this->mem[self::map_key_tags][$tag])
				)
					$this->mem[self::map_key_tags][$tag][] = $k;
			}
		}

		return $this->refresh();
	}

	/**
	 * Read key value from memory
	 * @param string|array $k
	 * @param $ttl_left
	 * @return mixed
	 */
	public function read($k, &$ttl_left = -1)
	{
		if (empty($k))
		{
			$this->ReportError('empty keys are not allowed', __LINE__);
			return false;
		}

		$this->readmemory();
		if (empty($this->mem)) return false;

		if (is_array($k))
		{
			$keys = array();
			$ttl_left = array();
			foreach ($k as $ki)
			{
				$ki = (string)$ki;
				if (!isset($this->mem[self::map_keys][$ki])) continue;
				$ttl_left[$ki] = $this->get_key_ttl($ki, $this->mem);
				if ($ttl_left[$ki] <= 0) continue;
				$keys[$ki] = $this->mem[self::map_keys][$ki];
				if (is_numeric($keys[$ki]))
				{
					if (intval($keys[$ki])==$keys[$ki]) $keys[$ki] = intval($keys[$ki]);
					else
					{
						if (floatval($keys[$ki])==$keys[$ki]) $keys[$ki] = floatval($keys[$ki]);
					}
				}
			}
			return $keys;
		}
		else
		{
			$k = (string)$k;
			$r = $this->mem[self::map_keys][$k];
			$ttl_left = $this->get_key_ttl($k, $this->mem);
			if ($ttl_left <= 0) $r = NULL;
			else
			{
				if (is_numeric($r))
				{
					if (intval($r)==$r) $r = intval($r);
					else
					{
						if (floatval($r)==$r) $r = floatval($r);
					}
				}
			}
			return $r;
		}
	}

	public function getSingleMemory()
	{
		$this->readmemory();
		if (!empty($this->mem[self::map_keys])) return $this->mem[self::map_keys];
		else return array();
	}

	/**
	 * Delete key from memory
	 * @param string $k
	 * @return bool
	 */
	public function del($k)
	{
		$auto_unlocker = NULL;
		if (!$this->sem->get_access_write($auto_unlocker))
		{
			return false;
		}
		$this->readmemory();
		if (empty($this->mem))
		{
			return false;
		}

		if (!is_array($k)) $k = array($k);
		foreach ($k as $key)
		{
			$key = (string)$key;
			unset($this->mem[self::map_keys][$key]);
			unset($this->mem[self::map_key_ttl][$key]);
			if (!empty($this->mem[self::map_key_tags]))
			{
				foreach ($this->mem[self::map_key_tags] as $tag_index => &$tag)
				{
					$indexes = array_keys($tag, $key);
					if (!empty($indexes))
					{
						foreach ($indexes as $index) unset($tag[$index]);
						if (empty($tag)) unset($this->mem[self::map_key_tags][$tag_index]);
					}
				}
			}
			unset($this->mem[self::map_key_locks][$key]);
		}

		return $this->refresh();
	}

	/** Add key to memory. If this key already exists - false will returned.
	 * Excludes simultaneously adding keys to exclude race condition.
	 * @param string $key
	 * @param mixed $value
	 * @param int $ttl
	 * @param string|array $tags
	 * @return bool|int
	 */
	public function add($key, $value, $ttl = 2592000, $tags = NULL)
	{
		if (empty($key)) return false;

		$auto_unlocker = NULL;
		if (!$this->sem->get_access_write($auto_unlocker))
		{
			return false;
		}

		$key = (string)$key;
		$this->readmemory();
		if (isset($this->mem[self::map_keys][$key]))
		{
			return false;
		}

		return $this->save($key, $value, $ttl, $tags);
	}

	/**
	 * Select from memory elements by function $fx
	 * @param callback $fx
	 * @param bool $get_array
	 * @return mixed
	 */
	public function select_fx($fx, $get_array = false)
	{
		$this->readmemory();
		if (empty($this->mem[self::map_keys])) return false;
		$arr = array();
		foreach ($this->mem[self::map_keys] as $index => $s)
		{
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
	 * Delete keys by tags
	 *
	 * @param array|string $tags - tag or array of tags
	 * @return boolean
	 */
	public function del_by_tags($tags)
	{
		if (empty($tags)) return false;
		if (!is_array($tags)) $tags = array($tags);

		$auto_unlocker = NULL;
		if (!$this->sem->get_access_write($auto_unlocker))
		{
			return false;
		}

		$this->readmemory();
		if (empty($this->mem[self::map_key_tags]))
		{
			return false;
		}

		$todel = array();
		foreach ($tags as $tag)
		{
			if (!empty($this->mem[self::map_key_tags][$tag])) $todel = array_merge($todel, $this->mem[self::map_key_tags][$tag]);
		}
		return $this->del($todel);
	}

	/**
	 * Delete old (by ttl) variables from storage
	 * @return boolean
	 */
	public function del_old()
	{
		$auto_unlocker = NULL;
		if (!$this->sem->get_access_write($auto_unlocker))
		{
			return false;
		}

		$this->readmemory();
		if (empty($this->mem) || empty($this->mem[self::map_key_ttl])) return false;

		$t = time();
		if (empty($this->mem[self::map_key_cleantime]) || ($t-$this->mem[self::map_key_cleantime]) > 1800)
		{
			foreach ($this->mem[self::map_key_ttl] as $key => $ttl)
			{
				if ($ttl < $t) unset($this->mem[self::map_keys][$key]);
			}
			$this->mem[self::map_key_cleantime] = $t;
			$this->refresh();
		}

		return true;
	}

	/** Return array of all stored keys */
	public function get_keys()
	{
		$this->readmemory();
		if (!empty($this->mem[self::map_keys])) return array_keys($this->mem[self::map_keys]);
		else return array();
	}

	/**
	 * Increment value of key
	 * @param string $key
	 * @param mixed $by_value
	 *							  if stored value is array:
	 *							  if $by_value is value in array, new element will be pushed to the end of array,
	 *							  if $by_value is key=>value array, key=>value pair will be added (or updated)
	 * @param int $limit_keys_count - maximum count of elements (used only if stored value is array)
	 * @param int $ttl
	 * @return int|string|array new value of key
	 */
	public function increment($key, $by_value = 1, $limit_keys_count = 0, $ttl = 259200)
	{
		if (empty($key))
		{
			$this->ReportError('empty keys are not allowed', __LINE__);
			return false;
		}
		$key = (string)$key;
		$auto_unlocker = NULL;
		if (!$this->sem->get_access_write($auto_unlocker))
		{
			return false;
		}

		$this->readmemory();
		if (!isset($this->mem[self::map_keys][$key])) return $this->save($key, $by_value);

		$value = $this->mem[self::map_keys][$key];
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

		$ttl = intval($ttl);
		if ($ttl > 0) $this->mem[self::map_key_ttl][$key] = time()+$ttl;
		$this->mem[self::map_keys][$key] = $value;
		$this->refresh();
		return $value;
	}

	/**
	 * Get exclusive mutex for key. Key will be still accessible to read and write, but
	 * another process can exclude dog-pile effect, if before updating the key he will try to get this mutex.
	 * @param mixed $key
	 * @param mixed $auto_unlocker_variable - pass empty, just declared variable
	 */
	public function lock_key($key, &$auto_unlocker_variable)
	{
		$auto_unlocker = NULL;
		if (!$this->sem->get_access_write($auto_unlocker))
		{
			return false;
		}
		$this->readmemory();
		$key = (string)$key;

		if (isset($this->mem[self::map_key_locks][$key]))
		{
			return false;
		}

		$this->mem[self::map_key_locks][$key] = 1;
		if ($this->refresh())
		{
			$auto_unlocker_variable = new \Jamm\Memory\KeyAutoUnlocker(array($this, 'unlock_key'));
			$auto_unlocker_variable->setKey($key);
			return true;
		}
		else return false;
	}

	/**
	 * Unlock key, locked by method 'lock_key'
	 * @param \Jamm\Memory\KeyAutoUnlocker $key_auto_unlocker
	 * @return bool
	 */
	public function unlock_key(\Jamm\Memory\KeyAutoUnlocker $key_auto_unlocker)
	{
		$key = $key_auto_unlocker->getKey();
		if (empty($key))
		{
			$this->ReportError('Empty key in the AutoUnlocker', __LINE__);
			return false;
		}
		$key_auto_unlocker->revoke();

		if (!$this->sem->get_access_write($auto_unlocker))
		{
			return false;
		}
		$this->readmemory();
		if (!isset($this->mem[self::map_keys][$key]))
		{
			$this->ReportError('key ['.$key.'] does not exists', __LINE__);
			return false;
		}

		if (isset($this->mem[self::map_key_locks][$key]))
		{
			unset($this->mem[self::map_key_locks][$key]);
			return $this->refresh();
		}
		return true;
	}

	public function setMutex(IMutex $mutex)
	{ $this->sem = $mutex; }

	abstract protected function readmemory();

	abstract protected function refresh();

	protected function get_key_ttl($key, &$mem)
	{
		$ttl_left = self::max_ttl;
		if (!empty($mem[self::map_key_ttl][$key]))
		{
			$ttl_left = $mem[self::map_key_ttl][$key]-time();
		}
		return $ttl_left;
	}
}
