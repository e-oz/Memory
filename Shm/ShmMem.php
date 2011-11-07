<?php
namespace Jamm\Memory\Shm;

class ShmMem extends SingleMemory
{
	protected $id = __FILE__;
	protected $shmsize = 70000;
	protected $max_size = 2097152;
	protected $shmkey = 0;
	protected $shm = 0;

	/**
	 * @param string $ID   by default is __FILE__
	 * @param int $size	initial size
	 * @param int $maxsize maximum allowed size
	 */
	public function __construct($ID = '', $size = 0, $maxsize = 0)
	{
		if (!empty($size)) $this->shmsize = $size;
		if (!empty($maxsize) && $maxsize > $this->shmsize) $this->max_size = $maxsize;
		$this->set_ID($ID);
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
		$sem = $this->sem;
		if (is_a($this->sem, 'MultiAccess'))
		{
			/** @var MultiAccess $sem */
			$q_read = msg_get_queue($sem->getReadQKey());
			if (!empty($q_read))
			{
				$q_stat = msg_stat_queue($q_read);
				$stat['readers'] = $q_stat['msg_qnum'];
				$stat['readers_qid'] = $sem->getReadQKey();
			}
			$q_writers = msg_get_queue($sem->getWriteQKey());
			if (!empty($q_writers))
			{
				$q_stat = msg_stat_queue($q_writers);
				$stat['writers'] = $q_stat['msg_qnum'];
				$stat['writers_qid'] = $sem->getWriteQKey();
			}
			$this->addErrLog($sem->getErrLog());
		}

		$this->readmemory();
		$stat['info'] = $this->mem[self::map_info];
		$stat['size'] = strlen(serialize($this->mem));
		$stat['max_size'] = shmop_size($this->shm);
		$stat['err_log'] = $this->getErrLog();

		return $stat;
	}

	public function set_ID($ID)
	{
		if (!empty($ID)) $this->id = $ID;
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

	public function get_ID()
	{
		return $this->id;
	}
}
