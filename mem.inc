<?php

class mem
{
	protected $id = __FILE__;
	protected $shmsize = 70000;
	protected $max_size = 2097152;
	protected $errlog = array();
	/** @var MultiAccess $sem */
	protected $sem;

	protected $mem = array();
	protected $shmkey = 0;
	protected $shm = 0;

	protected $ini = false;
	protected $readonly = false;

	/**
	 * Save key-value in memory.
	 * @param string|array $k (can be string or array of key=>value)
	 * @param mixed $v
	 * @param int $ttl
	 * @return bool
	 */
	public function save($k, $v = '', $ttl = 0)
	{
		if ($this->readonly==true) return false;
		$auto_unlocker = NULL;
		if (!$this->sem->get_access_write($auto_unlocker)) return false;
		if (!empty($k))
		{
			$this->readmemory();
			if (is_array($k))
			{
				foreach ($k as $ki => $kv)
				{
					$this->mem[$ki] = $kv;
					if ($ttl > 0) $this->mem['_ttl'][$ki] = time()+$ttl;
				}
			}
			else
			{
				$this->mem[$k] = $v;
				if ($ttl > 0) $this->mem['_ttl'][$k] = time()+$ttl;
			}
		}
		else $this->mem = $v;
		$r = $this->refresh();
		return $r;
	}

	/**
	 * Read key value from memory
	 * @param string|array $k
	 * @return mixed
	 */
	public function read($k = '')
	{
		if (!$this->ini) $this->ini();
		if (!$this->ini) return false;
		$auto_unlocker = NULL;
		if (!$this->sem->get_access_read($auto_unlocker)) return false;
		$this->readmemory();
		$this->sem->release_access_read($auto_unlocker);
		if (empty($k)) return $this->mem;
		if (is_array($k))
		{
			$keys = array();
			foreach ($k as $ki)
			{
				if (!empty($this->mem['_ttl'][$ki]) && $this->mem['_ttl'][$ki] < time()) continue;
				$keys[$ki] =& $this->mem[$ki]; //if (!is_string($ki) && !is_numeric($ki)) continue;
			}
			$r = $keys;
		}
		else
		{
			$r = $this->mem[$k];
			if (!empty($this->mem['_ttl'][$k]))
			{
				if ($this->mem['_ttl'][$k] < time()) $r = NULL;
			}
		}
		return $r;
	}

	/**
	 * Delete key from memory
	 * @param string $k
	 * @return bool
	 */
	public function del($k)
	{
		if (!$this->ini) $this->ini();
		if (!$this->ini) return false;
		if ($this->readonly==true) return false;
		$auto_unlocker = NULL;
		if (!$this->sem->get_access_write($auto_unlocker)) return false;
		$this->readmemory();
		unset($this->mem[$k]);
		$r = $this->refresh();
		return $r;
	}

	/** Add key to memory. If this key already exists - false will returned.
	 * Excludes simultaneously adding keys to exclude race condition.
	 * @param string $key
	 * @param mixed $value
	 * @param int $ttl
	 * @return bool|int
	 */
	public function add($key, $value, $ttl = 0)
	{
		if ($this->readonly==true) return false;
		if (empty($key)) return false;

		$auto_unlocker = NULL;
		if (!$this->sem->get_access_write($auto_unlocker)) return false;

		$this->readmemory();
		if (!empty($this->mem[$key])) return false;

		$this->mem[$key] = $value;
		if ($ttl > 0) $this->mem['_ttl'][$key] = time()+$ttl;

		return $this->refresh();
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
		if (!$this->ini) $this->ini();
		if (!$this->ini) return false;
		if (!is_array($params)) return false;
		$auto_unlocker = NULL;
		if (!$this->sem->get_access_read($auto_unlocker)) return false;
		$this->readmemory();
		$this->sem->release_access_read($auto_unlocker);
		if (empty($this->mem)) return false;
		$arr = array();
		foreach ($this->mem as $s)
		{
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
				else $arr[] = $s;
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
		if (!$this->ini) $this->ini();
		if (!$this->ini) return false;
		$auto_unlocker = NULL;
		if (!$this->sem->get_access_read($auto_unlocker)) return false;
		$this->readmemory();
		$this->sem->release_access_read($auto_unlocker);
		$arr = array();
		foreach ($this->mem as $index => $s)
		{
			if ($fx($s, $index)===true)
			{
				if (!$get_array) return $s;
				else $arr[$index] = $s;
			}
		}
		if (!$get_array || empty($arr)) return false;
		else return $arr;
	}

	public function get($k)
	{ return $this->read($k); }

	public function set($k, $v, $ttl = 0)
	{ return $this->save($k, $v, $ttl); }

	public function write($k, $v, $ttl = 0)
	{ return $this->save($k, $v, $ttl); }

	public function remove($k)
	{ return $this->del($k); }

	public function __construct($id = '', $size = 0, $maxsize = 0)
	{
		return $this->ini($id, $size, $maxsize);
	}

	public function __destruct()
	{
		return shmop_close($this->shm);
	}

	public function del_mem_block()
	{
		shmop_delete($this->shm);
		shmop_close($this->shm);
	}

	/** Initialization */
	public function ini($id = '', $size = 0, $maxsize = 0)
	{
		if ($this->ini==true) return true;
		if (!empty($id)) $this->id = $id;
		if (!empty($size)) $this->shmsize = $size;
		if (!empty($maxsize) && $maxsize > $this->shmsize) $this->max_size = $maxsize;
		if (is_string($this->id)) $this->shmkey = ftok($this->id, 'N'); //"N" because i love my son Nikita :)
		else $this->shmkey = $this->id;
		$this->shm = @shmop_open($this->shmkey, "w", 0, 0);
		if (!$this->shm)
		{
			$this->shm = @shmop_open($this->shmkey, "a", 0, 0);
			if ($this->shm!==false) $this->readonly = true;
		}
		//if memory not yet exists - lets create
		if (!$this->shm) $this->shm = shmop_open($this->shmkey, "n", 0777, $this->shmsize);
		if (!$this->shm) return false;
		$this->sem = new MultiAccess($this->id);
		$this->ini = true;
		return true;
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
			$this->mem['_info']['resized'] = $this->mem['_info']['resized']+1;
			$this->mem['_info']['resized_lasttime'] = time();
		}
		shmop_delete($this->shm);
		shmop_close($this->shm);
		$t = serialize($this->mem);
		$memsize = strlen($t);
		if ($memsize > $size) $size = $memsize+1000;
		$this->shm = shmop_open($this->shmkey, "n", 0777, $size);
		if (!$this->shm) return false; //mmm... oops.
		$w = shmop_write($this->shm, str_pad($t, shmop_size($this->shm), ' ', STR_PAD_RIGHT), 0);
		if (!$w) return false;
		return true;
	}

	/**
	 * Synchronyze data with memory storage
	 * @return bool|int
	 */
	protected function refresh()
	{
		if ($this->readonly==true) return false;
		ignore_user_abort(true);
		set_time_limit(180);
		//don't call readmemory() here
		if (!empty($this->mem['_ttl']) && intval(date('s'))==0)
		{
			$_time = time();
			foreach ($this->mem['_ttl'] as $ttl_key => $ttl_value)
			{
				if ($ttl_value < $_time) unset($this->mem[$ttl_key]);
			}
		}
		$t = serialize($this->mem);
		$size = strlen($t);
		$current_size = shmop_size($this->shm);
		if ($size > $current_size) $r = $this->resize($size+ceil($current_size/5)+1000);
		else $r = shmop_write($this->shm, str_pad($t, shmop_size($this->shm), ' ', STR_PAD_RIGHT), 0);
		return $r;
	}

	/**
	 * Read data from memory storage
	 * @return mixed
	 */
	protected function readmemory()
	{
		$this->mem = unserialize(trim(shmop_read($this->shm, 0, shmop_size($this->shm))));
		if (is_array($this->mem))
		{
			if (!isset($this->mem['_info']['created'])) $this->mem['_info']['created'] = time();
		}
		return true;
	}

	public function get_stat()
	{
		$stat['shm_id'] = $this->shm;
		$stat['shm_key'] = $this->shmkey;
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
		$mem = $this->read();
		$stat['info'] = $mem['_info'];
		$stat['size'] = strlen(serialize($mem));
		$stat['max_size'] = shmop_size($this->shm);

		$stat['err_log'] = array_merge($this->sem->getErrLog(), $this->errlog);
		return $stat;
	}
}
