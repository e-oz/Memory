<?php
namespace Jamm\Memory;

interface ISingleMemory extends IMemoryStorage
{
	public function getSingleMemory();

	public function setMutex(IMutex $mutex);
}

class SHMObject extends MemoryObject implements IMemoryStorage
{
	/** @var ShmMem */
	protected $mem_object;
	protected $shmsize = 102400;
	protected $id = __FILE__;
	protected $max_size = 10485760;
	protected $shm_data_id;
	protected $shm_data_key;
	protected $readonly = false;
	/** @var MultiAccess */
	protected $mutex;

	const map_key_start = 0;
	const map_key_fin = 1;
	const map_key_ttl = 2;
	const map_key_serialized = 3;
	const lock_key_prefix = '__lock_key_';
	const max_ttl = 2592000;

	/**
	 * @param string $id path to existing file, __FILE__ usually, will define scope (like prefix).
	 * @param integer $size initial size of the memory block in bites
	 * @param integer $maxsize
	 */
	public function __construct($id = '', $size = 0, $maxsize = 0)
	{
		if (!empty($id)) $this->id = $id;
		if (!empty($size)) $this->shmsize = $size;
		if (!empty($maxsize)) $this->max_size = $maxsize;

		//Create Mutex ("multiple read, one write")
		$this->mutex = new MultiAccess($this->id);

		//Create "shmop" to store data
		$this->shm_data_key = ftok($this->id, 'D'); //D - Data. But I still love my son Nikita ;)
		$this->shm_data_id = @shmop_open($this->shm_data_key, "w", 0, 0);
		if (!$this->shm_data_id)
		{
			$this->shm_data_id = @shmop_open($this->shm_data_key, "a", 0, 0);
			if ($this->shm_data_id!==false) $this->readonly = true;
		}

		//if memory not yet exists - lets create
		if (!$this->shm_data_id) $this->shm_data_id = shmop_open($this->shm_data_key, "n", 0777, $this->max_size);
		if (!$this->shm_data_id)
		{
			$this->ReportError('Can not create data segment in shared memory', __LINE__);
			return false;
		}

		//Create an mem-object to store the Map
		$map_id_key = ftok($this->id, 'h')+12;
		$this->mem_object = new ShmMem($map_id_key, $this->shmsize, $this->max_size);
		if (is_object($this->mem_object)) $this->ini = true;
		else
		{
			$this->ReportError('Can not create map', __LINE__);
			return false;
		}
		return $this->ini;
	}

	/**
	 * Add value to memory storage, only if this key does not exists (or false will be returned).
	 *
	 * @param string $k key
	 * @param mixed $v value
	 * @param integer $ttl Time To Live in seconds (value will be added to the current time)
	 * @param array|string $tags tag array of tags for this key
	 * @return bool
	 */
	public function add($k, $v, $ttl = 259200, $tags = NULL)
	{
		if (empty($k) || $v==NULL)
		{
			$this->ReportError('empty keys and values are not allowed', __LINE__);
			return false;
		}
		$k = (string)$k;
		$auto_unlocker = NULL;
		if (!$this->mutex->get_access_write($auto_unlocker))
		{
			$this->ReportError('write mutex not acquired', __LINE__);
			return false;
		}
		$map = $this->mem_object->read('map');
		if (isset($map[$k]))
		{
			$this->ReportError('key already exists', __LINE__);
			return false;
		}
		return $this->save($k, $v, $ttl, $tags);
	}

	/**
	 * Write data to storage directly
	 *
	 * @param string|array $data
	 * @param int|array $start
	 * @return bool
	 */
	protected function write_data($data, $start)
	{
		$r = 0;
		if (is_array($start) && is_array($data))
		{
			$i = 0;
			$c = sizeof($start);
			for (; $i < $c; $i++)
			{
				if (isset($data[$i]) && isset($start[$i])) $r += shmop_write($this->shm_data_id, $data[$i], $start[$i]);
			}
		}
		else $r = shmop_write($this->shm_data_id, $data, $start);
		return $r;
	}

	/**
	 * Save variable in memory storage
	 *
	 * @param string $k key
	 * @param mixed $v value
	 * @param integer $ttl Time To Live in seconds (value will be added to the current time)
	 * @param string|array $tags tag array of tags for this key
	 * @return bool
	 */
	public function save($k, $v, $ttl = 259200, $tags = NULL)
	{
		if (empty($k) || $v===NULL)
		{
			$this->ReportError('empty key and null value are not allowed', __LINE__);
			return false;
		}

		$k = (string)$k;
		$auto_unlocker = NULL;
		if (!$this->mutex->get_access_write($auto_unlocker))
		{
			$this->ReportError('write mutex not acquired', __LINE__);
			return false;
		}
		$map = $this->mem_object->read('map');
		$data_serialized = 0;
		if (!is_scalar($v))
		{
			$v = serialize($v);
			$data_serialized = 1;
		}
		$size = strlen($v);
		if (empty($map)) $start = 0;
		else
		{
			if (!array_key_exists($k, $map))
			{
				$start = $this->find_free_space($map, $size);
				if ($start===false)
				{
					$this->ReportError('Can not find enough space in memory', __LINE__);
					return false;
				}
			}
			else
			{
				if ($size <= ($map[$k][self::map_key_fin]-$map[$k][self::map_key_start])) $start = $map[$k][self::map_key_start];
				else
				{
					$this->del($k);
					$this->del_old();
					$map = $this->mem_object->read('map');
					$start = $this->find_free_space($map, $size);
					if ($start===false)
					{
						$this->ReportError('Can not find enough space in memory', __LINE__);
						return false;
					}
				}
			}
		}
		$r = $this->write_data($v, $start);
		if ($r===false) return false;
		$set_ttl = 0;
		$ttl = intval($ttl);
		if ($ttl > self::max_ttl) $ttl = self::max_ttl;
		if ($ttl > 0) $set_ttl = time()+$ttl;
		$map[$k] = array(self::map_key_start => $start, self::map_key_fin => ($start+$size));
		if ($set_ttl > 0) $map[$k][self::map_key_ttl] = $set_ttl;
		if ($data_serialized) $map[$k][self::map_key_serialized] = $data_serialized;
		$r = $this->mem_object->save('map', $map);
		if ($r===false)
		{
			$this->ReportError('map was not saved', __LINE__);
			return false;
		}
		if (!empty($tags))
		{
			if (!is_array($tags)) $tags = array($tags);
			$tags_was_changed = false;
			$mem_tags = $this->mem_object->read('tags');
			foreach ($tags as $tag)
			{
				if (empty($mem_tags[$tag]) || !in_array($k, $mem_tags[$tag]))
				{
					$mem_tags[$tag][] = $k;
					$tags_was_changed = true;
				}
			}
			if ($tags_was_changed) $this->mem_object->save('tags', $mem_tags);
		}
		return true;
	}

	/**
	 * Find free space in map to store data
	 * @param array $map
	 * @param int $size
	 * @return int
	 */
	protected function find_free_space(array $map, $size)
	{
		$c = count($map);
		if ($c < 1) return 0;
		$_end = $this->max_size;
		usort($map, array($this, 'sort_map'));
		$imap = array_values($map);
		$i = 0;
		$eoa = $c-1; //end of array
		if ($imap[0][0] > $size) return 0;
		for (; $i < $c; $i++)
		{
			$free_from = $imap[$i][self::map_key_fin]+1;
			if ($i==$eoa) $free_to = $_end;
			else $free_to = $imap[($i+1)][self::map_key_start]-1;
			if (($free_to-$free_from) >= $size) return $free_from;
		}
		$this->ReportError('Can not find enough space in memory', __LINE__);
		return false;
	}

	/**
	 * Sort map by start value at map
	 * This function are public only for the function "usort", she should not be used in interface.
	 * @access private
	 * @param array $a
	 * @param array $b
	 * @return int
	 */
	public function sort_map($a, $b)
	{
		if ($a[self::map_key_start]==$b[self::map_key_start]) return 0;
		if ($a[self::map_key_start] < $b[self::map_key_start]) return -1;
		else return 1;
	}

	/**
	 * Read data from memory storage
	 *
	 * @param string|array $k key or array of keys
	 * @param int $ttl_left = (ttl - time()) of key. Use to exclude dog-pile effect, with lock/unlock_key methods.
	 * @return mixed
	 */
	public function read($k, &$ttl_left = -1)
	{
		if (empty($k))
		{
			$this->ReportError('empty key are not allowed', __LINE__);
			return NULL;
		}
		$auto_unlocker = NULL;
		if (!$this->mutex->get_access_read($auto_unlocker))
		{
			$this->ReportError('read access mutex not acquired', __LINE__);
			return NULL;
		}
		$map = $this->mem_object->read('map');
		if (empty($map))
		{
			$this->ReportError('map are empty', __LINE__);
			return NULL;
		}

		if (is_array($k))
		{
			$todelete = array();
			$from_points = array();
			$to_points = array();
			foreach ($k as $key)
			{
				$key = (string)$key;
				if (!array_key_exists($key, $map)) continue;
				if (!empty($map[$key][self::map_key_ttl]) && $map[$key][self::map_key_ttl] < time())
				{
					$todelete[] = $key;
					continue;
				}
				$from_points[] = $map[$key][self::map_key_start];
				$to_points[] = $map[$key][self::map_key_fin];
			}
			if (!empty($todelete)) $this->del($todelete);
			$data = $this->read_data($from_points, $to_points, $k);
			if (!empty($data))
			{
				foreach ($data as $key => &$value)
				{
					if ($map[$key][self::map_key_serialized]==1) $value = unserialize($value);
					if (is_numeric($value))
					{
						if (intval($value)==$value) $value = intval($value);
						else
						{
							if (floatval($value)==$value) $value = floatval($value);
						}
					}
				}
			}
		}
		else
		{
			$k = (string)$k;
			if (!array_key_exists($k, $map))
			{
				$this->ReportError('key are not in map', __LINE__);
				return NULL;
			}
			$ttl_left = self::max_ttl;
			if (!empty($map[$k][self::map_key_ttl]))
			{
				$ttl_left = $map[$k][self::map_key_ttl]-time();
				if ($ttl_left <= 0)
				{
					$this->ReportError('ttl expired', __LINE__);
					$this->del($k);
					return NULL;
				}
			}

			$from = $map[$k][self::map_key_start];
			$to = $map[$k][self::map_key_fin];
			$data = $this->read_data($from, $to);
			if ($map[$k][self::map_key_serialized]==1) $data = unserialize($data);
			else
			{
				if (is_numeric($data))
				{
					if (intval($data)==$data) $data = intval($data);
					else
					{
						if (floatval($data)==$data) $data = floatval($data);
					}
				}
			}
		}
		return $data;
	}

	/** Return array of all stored keys */
	public function get_keys()
	{
		$map = array_keys($this->mem_object->read('map'));
		if (!is_array($map))
		{
			$this->ReportError('can not read map', __LINE__);
			return false;
		}
		$rebase = false;
		foreach ($map as $i => $key)
		{
			if (strpos($key, self::lock_key_prefix)===0 || strpos($key, '_ttl')===0 || strpos($key, '_info')===0)
			{
				$rebase = true;
				unset($map[$i]);
			}
		}
		if ($rebase) $map = array_unique($map);
		return $map;
	}

	/**
	 * Read data from storage directly
	 *
	 * @param int|array $from (integer or array of integers)
	 * @param int|array $to (integer or array of integers)
	 * @param array $keys
	 * @return string
	 */
	protected function read_data($from, $to, Array $keys = NULL)
	{
		if (is_array($from) && is_array($to) && !empty($keys))
		{
			$i = 0;
			$c = count($from);
			$data = array();
			for (; $i < $c; $i++)
			{
				if (isset($from[$i]) && isset($to[$i]) && isset($keys[$i])) $data[$keys[$i]] = shmop_read($this->shm_data_id, $from[$i], ($to[$i]-$from[$i]));
			}
		}
		else $data = shmop_read($this->shm_data_id, $from, ($to-$from));
		return $data;
	}

	/**
	 * Delete key or array of keys from storage (from map)
	 *
	 * @param string|array $k key or array of keys
	 * @return boolean
	 */
	public function del($k)
	{
		if ($k==NULL || $k=='')
		{
			$this->ReportError('Can not delete empty key', __LINE__);
			return false;
		}
		$auto_unlocker = NULL;
		if (!$this->mutex->get_access_write($auto_unlocker))
		{
			$this->ReportError('write mutex not acquired', __LINE__);
			return false;
		}
		$map = $this->mem_object->read('map');
		if (is_array($k))
		{
			foreach ($k as $key)
			{
				$key = (string)$key;
				unset($map[$key]);
			}
		}
		else
		{
			$k = (string)$k;
			unset($map[$k]);
		}
		$r = $this->mem_object->save('map', $map);
		if ($r===false)
		{
			$this->ReportError('map was not saved', __LINE__);
			return false;
		}
		return true;
	}

	/**
	 * Delete old (by ttl) variables from storage (map)
	 * @return boolean
	 */
	public function del_old()
	{
		$auto_unlocker = NULL;
		if (!$this->mutex->get_access_write($auto_unlocker))
		{
			$this->ReportError('write mutex was not acquired', __LINE__);
			return false;
		}
		$r = 0;
		$map = $this->mem_object->read('map');
		$todel = array();
		foreach ($map as $k => &$v)
		{
			if (!empty($v[self::map_key_ttl]) && $v[self::map_key_ttl] < time()) $todel[] = $k;
		}
		if (!empty($todel)) $r = $this->del($todel);
		return $r;
	}

	/**
	 * Delete keys by tags
	 *
	 * @param array|string $tag - tag or array of tags
	 * @return boolean
	 */
	public function del_by_tags($tag)
	{
		if (empty($tag))
		{
			$this->ReportError('empty value instead of tags given', __LINE__);
			return false;
		}
		$auto_unlocker = NULL;
		if (!$this->mutex->get_access_write($auto_unlocker))
		{
			$this->ReportError('write mutex not acquired', __LINE__);
			return false;
		}
		$mem_tags = $this->mem_object->read('tags');
		if (!is_array($tag)) $tag = array($tag);
		$todel = array();
		foreach ($tag as $t)
		{
			if (!empty($mem_tags[$t])) $todel = array_merge($todel, $mem_tags[$t]);
		}
		$r = $this->del($todel);
		return $r;
	}

	/**
	 * Select from storage by params
	 * k - key, r - relation, v - value
	 * example: select(array(array('k'=>'user_id',	'r'=>'<',	'v'=>1))); - select where user_id<1
	 * @deprecated
	 * @param array $params
	 * @param bool $get_array
	 * @return mixed
	 */
	public function select($params, $get_array = false)
	{
		$auto_unlocker = NULL;
		if (!$this->mutex->get_access_read($auto_unlocker))
		{
			$this->ReportError('read mutex not acquired', __LINE__);
			return false;
		}
		$map = $this->mem_object->read('map');
		$arr = array();
		foreach ($map as $key => &$zs)
		{
			if (!$zs[self::map_key_serialized]) continue;
			$s = $this->read($key);
			if (empty($s)) continue;
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
	 * Select from storage via callback function
	 *
	 * @param callback $fx ($value, $index)
	 * @param bool $get_array
	 * @return mixed
	 */
	public function select_fx($fx, $get_array = false)
	{
		$auto_unlocker = NULL;
		if (!$this->mutex->get_access_read($auto_unlocker))
		{
			$this->ReportError('read mutex not acquired', __LINE__);
			return false;
		}
		$map = $this->mem_object->read('map');
		$arr = array();
		foreach ($map as $index => &$zs)
		{
			if (!$zs[self::map_key_serialized]) continue;
			$s = $this->read($index);
			if (empty($s)) continue;
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
			$this->ReportError('empty key can not be incremented', __LINE__);
			return false;
		}
		$auto_unlocker = NULL;
		if (!$this->mutex->get_access_write($auto_unlocker))
		{
			$this->ReportError('write mutex not acquired', __LINE__);
			return false;
		}
		$map = $this->mem_object->read('map');
		if (!array_key_exists($key, $map))
		{
			if ($this->save($key, $by_value)) return $by_value;
			else return false;
		}
		$value = $this->read($key);
		if (is_array($value))
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
		elseif (is_numeric($value) && is_numeric($by_value)) $value += $by_value;
		else $value .= $by_value;
		$this->save($key, $value);
		return $value;
	}

	/**
	 * Returns statistic information
	 * @return array
	 */
	public function get_stat()
	{
		$stat = array();
		$map = $this->mem_object->read('map');
		$size = 0;
		if (!empty($map)) foreach ($map as $v) $size += ($v[self::map_key_fin]-$v[self::map_key_start]);
		$stat['size'] = $size;
		$q_read = msg_get_queue($this->mutex->getReadQKey());
		if (!empty($q_read))
		{
			$q_stat = msg_stat_queue($q_read);
			$stat['readers'] = $q_stat['msg_qnum'];
			$stat['readers_qid'] = $this->mutex->getReadQKey();
		}
		$q_writers = msg_get_queue($this->mutex->getWriteQKey());
		if (!empty($q_writers))
		{
			$q_stat = msg_stat_queue($q_writers);
			$stat['writers'] = $q_stat['msg_qnum'];
			$stat['writers_qid'] = $this->mutex->getWriteQKey();
		}
		$stat['shm_key'] = $this->shm_data_key;
		$stat['shm_id'] = $this->shm_data_id;
		$stat['max_size'] = $this->max_size;
		$stat['head'] = $this->mem_object->get_stat();
		$stat['err_log'] = $this->mutex->getErrLog();
		return $stat;
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
	 */
	public function lock_key($key, &$auto_unlocker_variable)
	{
		$r = $this->mem_object->add(self::lock_key_prefix.$key, 1);
		if (!$r) return false;
		$auto_unlocker_variable = new Key_AutoUnlocker(array($this, 'unlock_key'));
		$auto_unlocker_variable->key = $key;
		return true;
	}

	/**
	 * Unlock key, locked by method 'lock_key'
	 * @param Key_AutoUnlocker $auto_unlocker
	 * @return bool
	 */
	public function unlock_key(Key_AutoUnlocker $auto_unlocker)
	{
		if (empty($auto_unlocker->key))
		{
			$this->ReportError('autoUnlocker should be passed', __LINE__);
			return false;
		}
		$auto_unlocker->revoke();
		return $this->mem_object->del(self::lock_key_prefix.$auto_unlocker->key);
	}
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

	/**
	 * @param string $k key
	 * @param mixed $v value
	 * @param int $ttl time to live
	 * @param string|array $tags
	 * @return bool|void
	 */
	public function save($k, $v, $ttl = 2592000, $tags = NULL)
	{
		if (empty($k) || $v===NULL)
		{
			$this->ReportError('empty key and null value are not allowed', __LINE__);
			return false;
		}
		$k = (string)$k;
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
			$this->ReportError('can not acquire writers mutex', __LINE__);
			return false;
		}

		$key = (string)$key;
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
		$key = (string)$key;
		$auto_unlocker = NULL;
		if (!$this->sem->get_access_write($auto_unlocker))
		{
			$this->ReportError('can not acquire writers mutex', __LINE__);
			return false;
		}

		$this->readmemory();
		if (!isset($this->mem[self::map_keys][$key])) return $this->save($key, $by_value);

		$value = $this->mem[self::map_keys][$key];
		if (is_array($value))
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
		elseif (is_numeric($value) && is_numeric($by_value))
		{
			$value += $by_value;
		}
		else
		{
			$value .= $by_value;
		}

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
			$this->ReportError('can not acquire writers mutex', __LINE__);
			return false;
		}
		$this->readmemory();
		$key = (string)$key;

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
