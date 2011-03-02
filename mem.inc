<?php

interface ISingleMemory extends IMemoryStorage
{
	public function getSingleMemory();

	public function setMutex(IMutex $mutex);
}

abstract class SingleMemory extends MemoryObject implements ISingleMemory
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
	const max_ttl = 2592000;

	public function save($k, $v, $ttl = 2592000, $tags = NULL)
	{
		if (empty($k) || $v===NULL)
		{
			$this->ReportError('empty key and null value are not allowed', __LINE__);
			return false;
		}
		$auto_unlocker = NULL;
		if (!$this->sem->get_access_write($auto_unlocker))
		{
			$this->ReportError('can not acquire writers mutex', __LINE__);
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
					|| !in_array($k, $this->mem[self::map_key_tags][$tag]))
					$this->mem[self::map_key_tags][$tag][] = $k;
			}
		}

		return $this->refresh();
	}

	/**
	 * Read key value from memory
	 * @param string|array $k
	 * @return mixed
	 */
	public function read($k, &$ttl_left = -1)
	{
		if (empty($k))
		{
			$this->ReportError('empty key are not allowed', __LINE__);
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
			$this->ReportError('can not acquire writers mutex', __LINE__);
			return false;
		}
		$this->readmemory();
		if (empty($this->mem))
		{
			$this->ReportError('memory are empty', __LINE__);
			return false;
		}

		if (!is_array($k)) $k = array($k);
		foreach ($k as $key)
		{
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
	 * @return bool|int
	 */
	public function add($key, $value, $ttl = 2592000, $tags = NULL)
	{
		if (empty($key)) return false;

		$auto_unlocker = NULL;
		if (!$this->sem->get_access_write($auto_unlocker))
		{
			$this->ReportError('can not acquire writers mutex', __LINE__);
			return false;
		}

		$this->readmemory();
		if (isset($this->mem[self::map_keys][$key]))
		{
			$this->ReportError('key already exists', __LINE__);
			return false;
		}

		return $this->save($key, $value, $ttl, $tags);
	}

	/**
	 * Select from memory elements, where element[$k] in relation $r with value $v
	 * $k,$r and $v given in array $params
	 * if $get_array - return array of matched elements, else - first element.
	 * @param array $params
	 * @param bool $get_array
	 * @return mixed
	 */
	public function select($params, $get_array = false)
	{
		if (!is_array($params)) return false;
		$this->readmemory();
		if (empty($this->mem[self::map_keys])) return false;
		$arr = array();
		foreach ($this->mem[self::map_keys] as $key => &$s)
		{
			if (!is_array($s)) continue;
			$matched = true;
			foreach ($params as $p)
			{
				if ($p['r']=='=' || $p['r']=='==')
				{
					if ($s[$p['k']]!=$p['v'])
					{
						$matched = false;
						break;
					}
				}
				elseif ($p['r']=='<')
				{
					if ($s[$p['k']] >= $p['v'])
					{
						$matched = false;
						break;
					}
				}
				elseif ($p['r']=='>')
				{
					if ($s[$p['k']] <= $p['v'])
					{
						$matched = false;
						break;
					}
				}
				elseif ($p['r']=='<>' || $p['r']=='!=')
				{
					if ($s[$p['k']]==$p['v'])
					{
						$matched = false;
						break;
					}
				}
			}
			if ($matched==true)
			{
				if (!$get_array) return $s;
				else $arr[$key] = $s;
			}
		}
		if (!$get_array || empty($arr)) return false;
		else return $arr;
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
			$this->ReportError('can not acquire writers mutex', __LINE__);
			return false;
		}

		$this->readmemory();
		if (empty($this->mem[self::map_key_tags]))
		{
			$this->ReportError('tags was not found', __LINE__);
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
			$this->ReportError('can not acquire writers mutex', __LINE__);
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
	 * Increment
	 * @param string $key
	 * @param integer|string $by_value
	 * @return bool
	 */
	public function increment($key, $by_value = 1)
	{
		if (empty($key))
		{
			$this->ReportError('empty keys are not allowed', __LINE__);
			return false;
		}
		$auto_unlocker = NULL;
		if (!$this->sem->get_access_write($auto_unlocker))
		{
			$this->ReportError('can not acquire writers mutex', __LINE__);
			return false;
		}

		$this->readmemory();
		if (!isset($this->mem[self::map_keys][$key])) return $this->save($key, $by_value);

		if (is_numeric($this->mem[self::map_keys][$key]) && is_numeric($by_value))
		{
			$this->mem[self::map_keys][$key] += $by_value;
		}
		else
		{
			$this->mem[self::map_keys][$key] .= $by_value;
		}

		$value = $this->mem[self::map_keys][$key];

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
			$this->ReportError('can not acquire writers mutex', __LINE__);
			return false;
		}
		$this->readmemory();

		if (isset($this->mem[self::map_key_locks][$key]))
		{
			$this->ReportError('key locked', __LINE__);
			return false;
		}

		$this->mem[self::map_key_locks][$key] = 1;
		if ($this->refresh())
		{
			$auto_unlocker_variable = new Key_AutoUnlocker(array($this, 'unlock_key'));
			$auto_unlocker_variable->key = $key;
			return true;
		}
		else return false;
	}

	/**
	 * Unlock key, locked by method 'lock_key'
	 * @param Key_AutoUnlocker $key_auto_unlocker
	 * @return bool
	 */
	public function unlock_key(Key_AutoUnlocker $key_auto_unlocker)
	{
		if (empty($key_auto_unlocker->key))
		{
			$this->ReportError('autoUnlocker should be passed', __LINE__);
			return false;
		}
		$key_auto_unlocker->revoke();

		if (!$this->sem->get_access_write($auto_unlocker))
		{
			$this->ReportError('can not acquire writers mutex', __LINE__);
			return false;
		}
		$this->readmemory();
		if (!isset($this->mem[self::map_keys][$key_auto_unlocker->key]))
		{
			$this->ReportError('key ['.$key_auto_unlocker->key.'] does not exists', __LINE__);
			return false;
		}

		if (isset($this->mem[self::map_key_locks][$key_auto_unlocker->key]))
		{
			unset($this->mem[self::map_key_locks][$key_auto_unlocker->key]);
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

class ShmMem extends SingleMemory
{
	protected $id = __FILE__;
	protected $shmsize = 70000;
	protected $max_size = 2097152;
	protected $shmkey = 0;
	protected $shm = 0;

	public function __construct($id = '', $size = 0, $maxsize = 0)
	{
		if (!empty($id)) $this->id = $id;
		if (!empty($size)) $this->shmsize = $size;
		if (!empty($maxsize) && $maxsize > $this->shmsize) $this->max_size = $maxsize;

		if (is_string($this->id)) $this->shmkey = ftok($this->id, 'N'); //"N" because i love my son Nikita :)
		else $this->shmkey = $this->id;

		$this->shm = @shmop_open($this->shmkey, "w", 0, 0);
		if (!$this->shm)
		{
			$this->shm = @shmop_open($this->shmkey, "a", 0, 0);
			if ($this->shm!==false) $this->sem = new ReadOnlyAccess($this->id);
		}

		//if memory not yet exists - lets create
		if (!$this->shm) $this->shm = shmop_open($this->shmkey, "n", 0777, $this->shmsize);
		if (!$this->shm) return false;

		if (empty($this->sem)) $this->sem = new MultiAccess($this->id);
		return true;
	}

	public function __destruct()
	{
		shmop_close($this->shm);
	}

	public function del_mem_block()
	{
		shmop_delete($this->shm);
		shmop_close($this->shm);
	}

	/**
	 * Resize memory block
	 * @param int $size
	 * @return bool
	 */
	protected function resize($size)
	{
		if ($size > $this->max_size) return false;
		//should be called AFTER reading memory (to not loose changing of variables)
		if (empty($this->mem)) return false;
		ignore_user_abort(true);
		set_time_limit(180);
		if (is_array($this->mem))
		{
			$this->mem[self::map_info][self::map_info_resized] = $this->mem[self::map_info][self::map_info_resized]+1;
			$this->mem[self::map_info][self::map_info_resizetime] = time();
		}
		shmop_delete($this->shm);
		shmop_close($this->shm);
		$t = serialize($this->mem);
		$memsize = strlen($t);
		if ($memsize > $size) $size = $memsize+1000;
		$this->shm = shmop_open($this->shmkey, "n", 0777, $size);
		if (!$this->shm) return false; //mmm... oops.
		unset($this->mem);
		$w = shmop_write($this->shm, str_pad($t, shmop_size($this->shm), ' ', STR_PAD_RIGHT), 0);
		if (!$w) return false;
		return true;
	}

	/**
	 * Synchronize data with memory storage
	 * @return bool|int
	 */
	protected function refresh()
	{
		ignore_user_abort(true);
		set_time_limit(180);
		//don't call readmemory() here
		if (!empty($this->mem[self::map_key_ttl]) && intval(date('s'))==0)
		{
			$_time = time();
			foreach ($this->mem[self::map_key_ttl] as $ttl_key => $ttl_value)
			{
				if ($ttl_value < $_time) unset($this->mem[self::map_keys][$ttl_key]);
			}
		}
		$t = serialize($this->mem);
		$size = strlen($t);
		$current_size = shmop_size($this->shm);
		if ($size > $current_size) $r = $this->resize($size+ceil($current_size/5)+1000);
		else $r = shmop_write($this->shm, str_pad($t, shmop_size($this->shm), ' ', STR_PAD_RIGHT), 0);
		unset($this->mem);
		return $r;
	}

	/**
	 * Read data from memory storage
	 * @return mixed
	 */
	protected function readmemory()
	{
		if (empty($this->mem))
		{
			$auto_unlocker = NULL;
			if (!$this->sem->get_access_read($auto_unlocker))
			{
				$this->ReportError('can not acquire readers access', __LINE__);
				return false;
			}
			$this->mem = unserialize(trim(shmop_read($this->shm, 0, shmop_size($this->shm))));
			$this->sem->release_access_read($auto_unlocker);
		}
		return true;
	}

	public function get_stat()
	{
		$stat['shm_id'] = $this->shm;
		$stat['shm_key'] = $this->shmkey;
		if (is_a($this->sem, 'MultiAccess'))
		{
			$q_read = msg_get_queue($this->sem->getReadQKey());
			if (!empty($q_read))
			{
				$q_stat = msg_stat_queue($q_read);
				$stat['readers'] = $q_stat['msg_qnum'];
				$stat['readers_qid'] = $this->sem->getReadQKey();
			}
			$q_writers = msg_get_queue($this->sem->getWriteQKey());
			if (!empty($q_writers))
			{
				$q_stat = msg_stat_queue($q_writers);
				$stat['writers'] = $q_stat['msg_qnum'];
				$stat['writers_qid'] = $this->sem->getWriteQKey();
			}
			$this->err_log = array_merge($this->sem->getErrLog(), $this->err_log);
		}

		$this->readmemory();
		$stat['info'] = $this->mem[self::map_info];
		$stat['size'] = strlen(serialize($this->mem));
		$stat['max_size'] = shmop_size($this->shm);
		$stat['err_log'] = $this->err_log;

		return $stat;
	}

}

class DummyMutex implements IMutex
{

	public function get_access_read(&$auto_unlocker_reference)
	{ return true; }

	public function get_access_write(&$auto_unlocker_reference)
	{ return true; }

	public function release_access_read(IRevokable $autoUnlocker = NULL)
	{ return true; }

	public function release_access_write(IRevokable $autoUnlocker = NULL)
	{ return true; }
}

class ReadOnlyAccess extends MultiAccess
{
	public function get_access_write(&$auto_unlocker_reference)
	{ return false; }
}
