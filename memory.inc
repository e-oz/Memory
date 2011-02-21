<?php

/**
 * @author OZ
 * @copyright 2010-2011
 * license MIT
 */

class Key_AutoUnlocker
{
	public $key = '';
	protected $Unlock = NULL;

	/**
	 * @param callback $Unlock
	 */
	public function __construct($Unlock)
	{
		if (is_callable($Unlock)) $this->Unlock = $Unlock;
	}

	public function revoke()
	{
		$this->Unlock = NULL;
	}

	public function __destruct()
	{
		if (isset($this->Unlock)) call_user_func($this->Unlock, $this);
	}
}

interface IMemoryStorage
{
	public function add($k, $v, $ttl = 259200, $tags = NULL);

	public function save($k, $v, $ttl = 259200, $tags = NULL);

	public function read($k = NULL, &$ttl_left = -1);

	public function del($k);

	public function del_old(Array $with_keys = NULL);

	public function del_by_tags($tag);

	public function select($params, $get_array = false);

	public function select_fx($fx, $get_array = false);

	public function increment($key, $by_value = 1);

	public function get_stat();

	public function lock_key($key, &$auto_unlocker_variable);

	public function unlock_key(Key_AutoUnlocker $auto_unlocker);
}

class Memory
{
	/** @var APCObject */
	private static $memory_object;

	public static function getMemoryObject()
	{
		if (empty(self::$memory_object))
		{
			self::$memory_object = new APCObject('memory');
		}
		return self::$memory_object;
	}

	/**
	 * Add value to memory storage, only if this key does not exists (or false will be returned).
	 *
	 * @param string $k
	 * @param mixed $v
	 * @param integer $ttl
	 * @return boolean
	 */
	public static function add($k, $v, $ttl = 259200, $tags = NULL)
	{
		return self::getMemoryObject()->add($k, $v, $ttl, $tags);
	}

	/**
	 * Save variable in memory storage
	 *
	 * @param string $k
	 * @param mixed $v
	 * @param integer $ttl - time to live (store) in seconds
	 * @param array $tags - array of tags for this key
	 * @return bool
	 */
	public static function save($k, $v, $ttl = 259200, $tags = NULL)
	{
		return self::getMemoryObject()->save($k, $v, $ttl, $tags);
	}

	/**
	 * Read data from memory storage
	 *
	 * @param mixed $k (string or array of string keys)
	 * @param mixed $ttl_left = (ttl - time()) of key. Use to exclude dog-pile effect, with lock/unlock_key methods.
	 * @return mixed
	 */
	public static function read($k = NULL, &$ttl_left = -1)
	{
		return self::getMemoryObject()->read($k, $ttl_left);
	}

	/**
	 * Delete key or array of keys from storage (from map)
	 *
	 * @param mixed $k
	 * @return boolean
	 */
	public static function del($k)
	{
		return self::getMemoryObject()->del($k);
	}

	/**
	 * Delete old (by ttl) variables from storage (map)
	 *
	 * @return boolean
	 */
	public static function del_old(Array $with_keys = NULL)
	{
		return self::getMemoryObject()->del_old($with_keys);
	}

	/**
	 * Delete keys by tags
	 *
	 * @param array|string $tag - tag or array of tags
	 * @return boolean
	 */
	public static function del_by_tags($tag)
	{
		return self::getMemoryObject()->del_by_tags($tag);
	}

	/**
	 * Select from storage by params
	 * k - key, r - relation, v - value
	 * example: select(array(array('k'=>'user_id',	'r'=>'<',	'v'=>1))); - select where user_id<1
	 * @param array $params
	 * @param bool $get_array
	 * @return mixed
	 */
	public static function select($params, $get_array = false)
	{
		return self::getMemoryObject()->select($params, $get_array);
	}

	/**
	 * Select from storage via callback function
	 *
	 * @param callback $fx
	 * @param bool $get_array
	 * @return mixed
	 */
	public static function select_fx($fx, $get_array = false)
	{
		return self::getMemoryObject()->select_fx($fx, $get_array);
	}

	/**
	 * Increment
	 *
	 * @param mixed $key
	 * @param integer $by_value
	 * @return bool
	 */
	public static function increment($key, $by_value = 1)
	{
		return self::getMemoryObject()->increment($key, $by_value);
	}

	public static function get_stat()
	{
		return self::getMemoryObject()->get_stat();
	}

	public static function ini(IMemoryStorage $MemoryObject = NULL)
	{
		if (is_object($MemoryObject)) self::$memory_object = $MemoryObject;
	}

	/**
	 * Get exclusive mutex for key. Key will be still accessible to read and write, but
	 * another process can exclude dog-pile effect, if before updating the key he will try to get this mutex.
	 * Example:
	 * Process 1 reads key simultaneously with Process 2.
	 * Value of this key are too old, so Process 1 going to refresh it. Simultaneously with Process 2.
	 * But both of them trying to lock_key, and Process 1 only will refresh value of key (taking it from database, e.g.),
	 * and Process 2 can decide, what he want to do - use old value and not spent time to database, or something else.
	 * @static
	 * @param mixed $key
	 * @param mixed $auto_unlocker_variable - pass empty, just declared variable
	 */
	public static function lock_key($key, &$auto_unlocker_variable)
	{
		return self::getMemoryObject()->lock_key($key, $auto_unlocker_variable);
	}

	/**
	 * Unlock key, locked by method 'lock_key'
	 * @static
	 * @param Key_AutoUnlocker $auto_unlocker
	 * @return bool
	 */
	public static function unlock_key(Key_AutoUnlocker $auto_unlocker)
	{
		return self::getMemoryObject()->unlock_key($auto_unlocker);
	}
}

class MemoryObject implements IMemoryStorage
{
	/** @var mem */
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
	 * Initialize (constructor)
	 *
	 * @param string $id
	 * @param integer $size
	 * @param integer $maxsize
	 * @return boolean
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
		if (!$this->shm_data_id) return false;

		//Create an mem-object to store the Map
		$map_id_key = ftok($this->id, 'h')+12;
		$this->mem_object = new mem($map_id_key, $this->shmsize, $this->max_size);
		if (is_object($this->mem_object)) $this->ini = true;
		else return false;
		return $this->ini;
	}

	/**
	 * Add value to memory storage, only if this key does not exists (or false will be returned).
	 *
	 * @param string $k
	 * @param mixed $v
	 * @param integer $ttl
	 * @return boolean
	 */
	public function add($k, $v, $ttl = 259200, $tags = NULL)
	{
		if (empty($k) || $v==NULL) return false;
		$auto_unlocker = NULL;
		if (!$this->mutex->get_access_write($auto_unlocker)) return false;
		$map = $this->mem_object->read('map');
		if (array_key_exists($k, $map)) return false;
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
			$start = $this->find_free_space($map, $size);
			if ($start===false) return false;
		}
		$r = $this->write_data($v, $start);
		if ($r===false) return false;
		$set_ttl = 0;
		if ($ttl > 0 && $ttl < 2592000) $set_ttl = time()+$ttl;
		$map[$k] = array(self::map_key_start => $start, self::map_key_fin => ($start+$size), self::map_key_ttl => $set_ttl, self::map_key_serialized => $data_serialized);
		$r = $this->mem_object->save('map', $map);
		if ($r===false) return false;
		if (!empty($tags) && is_array($tags))
		{
			$mem_tags = $this->mem_object->read('tags');
			foreach ($tags as $tag)
			{
				if (empty($mem_tags[$tag]) || !in_array($k, $mem_tags[$tag])) $mem_tags[$tag][] = $k;
			}
			$this->mem_object->save('tags', $mem_tags);
		}
		return true;
	}

	/**
	 * Write data to storage directly
	 * @access private
	 * @param mixed $data (string or array)
	 * @param mixed $start (integer or array)
	 * @return bool
	 */
	protected function write_data(&$data, $start)
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
	 * @param string $k
	 * @param mixed $v
	 * @param integer $ttl - time to live (store) in seconds
	 * @param array $tags - array of tags for this key
	 * @return bool
	 */
	public function save($k, $v, $ttl = 259200, $tags = NULL)
	{
		if (empty($k) || $v===NULL) return false;
		$auto_unlocker = NULL;
		if (!$this->mutex->get_access_write($auto_unlocker)) return false;
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
				if ($start===false) return false;
			}
			else
			{
				if ($size <= ($map[$k][self::map_key_fin]-$map[$k][self::map_key_start])) $start = $map[$k][self::map_key_start];
				else
				{
					$this->del_old(array($k));
					$map = $this->mem_object->read('map');
					$start = $this->find_free_space($map, $size);
					if ($start===false) return false;
				}
			}
		}
		$r = $this->write_data($v, $start);
		if ($r===false) return false;
		$set_ttl = 0;
		if ($ttl > 0 && $ttl <= self::max_ttl) $set_ttl = time()+$ttl;
		$map[$k] = array(self::map_key_start => $start, self::map_key_fin => ($start+$size), self::map_key_ttl => $set_ttl, self::map_key_serialized => $data_serialized);
		$r = $this->mem_object->save('map', $map);
		if ($r===false) return false;
		if (!empty($tags))
		{
			if (!is_array($tags))
			{
				if (is_scalar($tags)) $tags = array($tags);
				else goto tags_if_end;
			}
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
			tags_if_end:
		}
		return true;
	}

	/**
	 * Find free space in map to store data
	 * @access private
	 * @param mixed $map
	 * @param mixed $size
	 * @return int
	 */
	protected function find_free_space(array $map, $size)
	{
		$c = count($map);
		if ($c < 1) return 0;
		$_end = $this->max_size;
		usort($map, array(__CLASS__, 'sort_map'));
		$imap = array();
		foreach ($map as &$v) $imap[] = $v;
		$i = 0;
		$eoa = $c-1; //end of array
		if ($imap[0][0] > $size) return 0;
		for (; $i < $c; $i++)
		{
			$free_from = $imap[$i][self::map_key_fin]+1;
			if ($i==$eoa) $free_to = $_end;
			else $free_to = $imap[($i+1)][self::map_key_start]-1;
			if (($free_to-$free_from) > $size) return $free_from;
		}
		return false;
	}

	/**
	 * Sort map by start value at map
	 * This function are public only for the function "usort", she should not be used in interface.
	 * @param mixed $a
	 * @param mixed $b
	 * @return mixed
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
	 * @param mixed $k (string or array of string keys)
	 * @param mixed $ttl_left = (ttl - time()) of key. Use to exclude dog-pile effect, with lock/unlock_key methods.
	 * @return mixed
	 */
	public function read($k = NULL, &$ttl_left = -1)
	{
		$auto_unlocker = NULL;
		if (!$this->mutex->get_access_read($auto_unlocker)) return NULL;
		$map = $this->mem_object->read('map');
		if (empty($map)) return NULL;
		if (empty($k)) return $map;
		if (is_array($k))
		{
			$todelete = array();
			$from_points = array();
			$to_points = array();
			foreach ($k as $key)
			{
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
				}
			}
		}
		else
		{
			if (!array_key_exists($k, $map)) return NULL;
			if (!empty($map[$k][self::map_key_ttl]))
			{
				if ($map[$k][self::map_key_ttl] < time())
				{
					$this->del($k);
					return NULL;
				}
				else
				{
					$ttl_left = $map[$k][self::map_key_ttl]-time();
				}
			}
			else $ttl_left = self::max_ttl;
			$from = $map[$k][self::map_key_start];
			$to = $map[$k][self::map_key_fin];
			$data = $this->read_data($from, $to);
			if ($map[$k][self::map_key_serialized]==1) $data = unserialize($data);
		}
		return $data;
	}

	/**
	 * Read data from storage directly
	 *
	 * @param mixed $from (integer or array of integers)
	 * @param mixed $to (integer or array of integers)
	 * @return
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
	 * @param mixed $k
	 * @return boolean
	 */
	public function del($k)
	{
		if ($k==NULL || $k=='') return false;
		$auto_unlocker = NULL;
		if (!$this->mutex->get_access_write($auto_unlocker)) return false;
		$map = $this->mem_object->read('map');
		if (is_array($k))
		{
			foreach ($k as $key) unset($map[$key]);
		}
		else unset($map[$k]);
		$r = $this->mem_object->save('map', $map);
		if ($r===false) return false;
		return true;
	}

	/**
	 * Delete old (by ttl) variables from storage (map)
	 *
	 * @return boolean
	 */
	public function del_old(Array $with_keys = NULL)
	{
		$auto_unlocker = NULL;
		if (!$this->mutex->get_access_write($auto_unlocker)) return false;
		$r = 0;
		$map = $this->mem_object->read('map');
		$todel = array();
		foreach ($map as $k => &$v)
		{
			if (!empty($v[self::map_key_ttl]) && $v[self::map_key_ttl] < time()) $todel[] = $k;
		}
		if (!empty($with_keys)) $todel = array_merge($todel, $with_keys);
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
		if (empty($tag)) return false;
		$auto_unlocker = NULL;
		if (!$this->mutex->get_access_write($auto_unlocker)) return false;
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
	 * @param array $params
	 * @param bool $get_array
	 * @return mixed
	 */
	public function select($params, $get_array = false)
	{
		$auto_unlocker = NULL;
		if (!$this->mutex->get_access_read($auto_unlocker)) return false;
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
				else $arr[] = $s;
			}
		}
		if (!$get_array || empty($arr)) return false;
		else return $arr;
	}

	/**
	 * Select from storage via callback function
	 *
	 * @param callback $fx
	 * @param bool $get_array
	 * @return mixed
	 */
	public function select_fx($fx, $get_array = false)
	{
		$auto_unlocker = NULL;
		if (!$this->mutex->get_access_read($auto_unlocker)) return false;
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
	 * Increment
	 *
	 * @param mixed $key
	 * @param integer $by_value
	 * @return bool
	 */
	public function increment($key, $by_value = 1)
	{
		if (empty($key)) return false;
		$auto_unlocker = NULL;
		if (!$this->mutex->get_access_write($auto_unlocker)) return false;
		$map = $this->mem_object->read('map');
		if (!array_key_exists($key, $map))
		{
			return $this->save($key, $by_value);
		}
		$value = $this->read($key);
		if (is_numeric($value) && is_numeric($by_value)) $value += $by_value;
		else $value .= $by_value;
		$this->save($key, $value);
		return true;
	}

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
	 * @static
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
	 * @static
	 * @param Key_AutoUnlocker $auto_unlocker
	 * @return bool
	 */
	public function unlock_key(Key_AutoUnlocker $auto_unlocker)
	{
		if (empty($auto_unlocker->key)) return false;
		$auto_unlocker->revoke();
		return $this->mem_object->del(self::lock_key_prefix.$auto_unlocker->key);
	}

}

class APCObject implements IMemoryStorage
{
	protected $prefix = 'K'; //because I love my wife Katya :)
	protected $map_prefix;
	protected $lock_key_prefix;
	protected $defragmentation_prefix;

	const map_prefix = '.map.';
	const lock_key_prefix = '.lock_key.';
	const defragmentation_prefix = '.clean.';
	const max_ttl = 2592000;
	const map_ttl = 'l';
	const map_tags = 't';
	const map_array = 's';

	/**
	 * @param string $prefix
	 */
	public function __construct($prefix = '')
	{
		if (!empty($prefix))
		{
			$this->prefix = str_replace('.', '_', $prefix).'.';
		}

		$this->map_prefix = self::map_prefix.$prefix;
		$this->lock_key_prefix = self::lock_key_prefix.$prefix;
		$this->defragmentation_prefix = self::defragmentation_prefix.$prefix;
	}

	/**
	 * Add value to memory storage, only if this key does not exists (or false will be returned).
	 *
	 * @param string $k
	 * @param mixed $v
	 * @param integer $ttl
	 * @param array|string $tags
	 * @return boolean
	 */
	public function add($k, $v, $ttl = 259200, $tags = NULL)
	{
		if (empty($k) || $v==NULL) return false;
		if ($ttl > self::max_ttl) $ttl = self::max_ttl;

		$add = apc_add($this->prefix.$k, $v, $ttl);
		if (!$add) return false;

		$this->map_save($k, $tags, $ttl, is_array($v));

		return true;
	}

	/**
	 * @param string $key
	 * @param string|array $tags
	 * @return bool
	 */
	public function set_tags($key, $tags)
	{
		if (!is_array($tags))
		{
			if (is_scalar($tags)) $tags = array($tags);
			else $tags = array();
		}
		return $this->map_save($key, $tags);
	}

	/**
	 * Save variable in memory storage
	 *
	 * @param string $k
	 * @param mixed $v
	 * @param integer $ttl - time to live (store) in seconds
	 * @param array|string $tags - array of tags for this key
	 * @return bool
	 */
	public function save($k, $v, $ttl = 259200, $tags = NULL)
	{
		if (empty($k)) return false;
		if ($ttl > self::max_ttl) $ttl = self::max_ttl;

		$this->del_old();

		if (!apc_store($this->prefix.$k, $v, $ttl)) return false;

		$this->map_save($k, $tags, $ttl, is_array($v));
		return true;
	}

	/**
	 * Read data from memory storage
	 *
	 * @param string|array $k (string or array of string keys)
	 * @param mixed $ttl_left = (ttl - time()) of key. Use to exclude dog-pile effect, with lock/unlock_key methods.
	 * @return mixed
	 */
	public function read($k = NULL, &$ttl_left = -1)
	{
		if (empty($k)) return NULL;
		if (is_array($k))
		{
			$toget = array();
			foreach ($k as $key) $toget[] = $this->prefix.$key;
			$data = apc_fetch($toget);
		}
		else
		{
			$data = apc_fetch($this->prefix.$k, $success);
			if (!$success) return false;
			if ($ttl_left!=-1)
			{
				$map = apc_fetch($this->map_prefix);
				if (!empty($map[$k][self::map_ttl])) $ttl_left = $map[$k][self::map_ttl]-time();
				else $ttl_left = self::max_ttl;
			}
		}
		return $data;
	}

	/**
	 * Delete key or array of keys from storage (from map)
	 * @param string|array $k
	 * @return boolean
	 */
	public function del($k)
	{
		if (empty($k)) return false;
		$this->map_delete($k);
		if (is_array($k))
		{
			$todel = array();
			foreach ($k as $key) $todel[] = $this->prefix.$key;
			return apc_delete($todel);
		}
		else
		{
			return apc_delete($this->prefix.$k);
		}
	}

	/**
	 * Delete old (by ttl) variables from storage
	 * It's very important function to prevent APC's cache fragmentation.
	 * @return boolean
	 */
	public function del_old(Array $with_keys = NULL)
	{
		$t = time();
		$previous_clean = apc_fetch($this->defragmentation_prefix);
		if (empty($previous_clean) || ($t-$previous_clean) > 1800)
		{
			apc_store($this->defragmentation_prefix, $t, 1800);

			$map = apc_fetch($this->map_prefix, $success);
			if (!$success) return false;

			$todel = array();
			foreach ($map as $key => $m)
			{
				if ($m[self::map_ttl] > 0 && ($m[self::map_ttl] < $t)) $todel[] = $key;
			}
			if (!empty($todel)) $this->del($todel);
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
		$map = apc_fetch($this->map_prefix, $success);
		if (!$success) return false;
		$todel = array();
		foreach ($map as $key => $k)
		{
			if (!empty($k[self::map_tags]))
			{
				foreach ($tags as $tag)
				{
					if (in_array($tag, $k[self::map_tags]))
					{
						$todel[] = $key;
						break;
					}
				}
			}
		}
		if (!empty($todel)) $this->del($todel);
		return true;
	}

	/**
	 * Select from storage by params
	 * k - key, r - relation, v - value
	 * example: select(array(array('k'=>'user_id',	'r'=>'<',	'v'=>1))); - select where user_id<1
	 * @param array $params
	 * @param bool $get_array
	 * @return mixed
	 */
	public function select($params, $get_array = false)
	{
		$arr = array();
		$map = apc_fetch($this->map_prefix, $success);
		if (!$success) return false;

		foreach ($map as $key => &$zs)
		{
			if (empty($zs[self::map_array])) continue;
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
				else $arr[] = $s;
			}
		}
		if (!$get_array || empty($arr)) return false;
		else return $arr;
	}

	/**
	 * Select from storage via callback function
	 *
	 * @param callback $fx
	 * @param bool $get_array
	 * @return mixed
	 */
	public function select_fx($fx, $get_array = false)
	{
		$map = apc_fetch($this->map_prefix, $success);
		if (!$success) return false;

		$arr = array();
		foreach ($map as $index => &$zs)
		{
			if (empty($zs[self::map_array])) continue;
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
	 * Increment
	 * @param mixed $key
	 * @param integer $by_value
	 * @return bool
	 */
	public function increment($key, $by_value = 1)
	{
		if (empty($key)) return false;
		$value = apc_fetch($this->prefix.$key, $success);
		if (!$success)
		{
			return $this->save($key, $by_value);
		}
		if (is_numeric($value) && is_numeric($by_value)) return apc_inc($this->prefix.$key, $by_value);
		else
		{
			$value .= $by_value;
			return $this->save($key, $value);
		}

	}

	public function get_stat()
	{
	}

	/**
	 * Get exclusive mutex for key. Key will be still accessible to read and write, but
	 * another process can exclude dog-pile effect, if before updating the key he will try to get this mutex.
	 * Example:
	 * Process 1 reads key simultaneously with Process 2.
	 * Value of this key are too old, so Process 1 going to refresh it. Simultaneously with Process 2.
	 * But both of them trying to lock_key, and Process 1 only will refresh value of key (taking it from database, e.g.),
	 * and Process 2 can decide, what he want to do - use old value and not spent time to database, or something else.
	 * @static
	 * @param mixed $key
	 * @param mixed $auto_unlocker_variable - pass empty, just declared variable
	 */
	public function lock_key($key, &$auto_unlocker_variable)
	{
		$r = apc_add($this->lock_key_prefix.$key, 1, 30);
		if (!$r) return false;
		$auto_unlocker_variable = new Key_AutoUnlocker(array($this, 'unlock_key'));
		$auto_unlocker_variable->key = $key;
		return true;
	}

	/**
	 * Unlock key, locked by method 'lock_key'
	 * @static
	 * @param Key_AutoUnlocker $auto_unlocker
	 * @return bool
	 */
	public function unlock_key(Key_AutoUnlocker $auto_unlocker)
	{
		if (empty($auto_unlocker->key)) return false;
		$auto_unlocker->revoke();
		return apc_delete($this->lock_key_prefix.$auto_unlocker->key);
	}

	/**
	 * @param string $key
	 * @param int $ttl
	 * @param string|array $tags
	 * @return bool
	 */
	protected function map_save($key, $tags, $ttl = -1, $is_array = null)
	{
		$map = apc_fetch($this->map_prefix);
		if (!is_array($tags))
		{
			if (is_scalar($tags)) $tags = array($tags);
			else $tags = null;
		}

		if ($ttl > 0) $ttl = time()+$ttl;

		if (isset($map[$key]))
		{
			$old = $map[$key];
			if (!is_bool($is_array)) $is_array = $map[$key][self::map_array];
			if ($ttl < 0) $ttl = $map[$key][self::map_ttl];
		}
		else
		{
			$old = array();
			if ($ttl < 0) $ttl = self::max_ttl;
		}

		$map[$key] = array(self::map_ttl => $ttl);
		if (!empty($tags)) $map[$key][self::map_tags] = $tags;
		if (!empty($is_array)) $map[$key][self::map_array] = $is_array;

		if ($old!=$map[$key])
		{
			return apc_store($this->map_prefix, $map);
		}
		return true;
	}

	protected function map_delete($keys)
	{
		if (!is_array($keys)) $keys = array($keys);
		$map = apc_fetch($this->map_prefix, $success);
		if (!$success) return false;
		foreach ($keys as $key) unset($map[$key]);
		return apc_store($this->map_prefix, $map);
	}
}
