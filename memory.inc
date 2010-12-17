<?php

/**
 * @author OZ
 * @copyright 2010
 */

if (!class_exists('MultiAccess')) include_once __DIR__.'/MultiAccess.inc';
if (!class_exists('mem')) include_once __DIR__.'/mem.inc';

class Key_AutoUnlocker extends autoUnlocker
{
	public $key = '';
}

class Memory
{
	/** @var mem */
	public static $mem_object;
	public static $shmsize = 102400;
	public static $id = __FILE__;
	public static $max_size = 10485760;
	public static $prefix = '';

	private static $shm_data_id;
	private static $shm_data_key;
	private static $ini = false;
	private static $readonly = false;
	private static $mutex;

	const map_key_start = 0;
	const map_key_fin = 1;
	const map_key_ttl = 2;
	const map_key_serialized = 3;
	const lock_key_prefix = '__lock_key_';

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
		if (!self::$ini) self::ini();
		if (!self::$ini) return false;
		if (empty($k) || $v==NULL) return false;
		$auto_unlocker = NULL;
		if (!self::$mutex->get_access_write($auto_unlocker)) return false;
		$map = self::$mem_object->read('map');
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
			$start = self::find_free_space($map, $size);
			if ($start===false) return false;
		}
		$r = self::write_data($v, $start);
		if ($r===false) return false;
		$set_ttl = 0;
		if ($ttl > 0 && $ttl < 2592000) $set_ttl = time()+$ttl;
		$map[$k] = array(self::map_key_start => $start, self::map_key_fin => ($start+$size), self::map_key_ttl => $set_ttl, self::map_key_serialized => $data_serialized);
		$r = self::$mem_object->save('map', $map);
		if ($r===false) return false;
		if (!empty($tags) && is_array($tags))
		{
			$mem_tags = self::$mem_object->read('tags');
			foreach ($tags as $tag)
			{
				if (empty($mem_tags[$tag]) || !in_array($k, $mem_tags[$tag])) $mem_tags[$tag][] = $k;
			}
			self::$mem_object->save('tags', $mem_tags);
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
	private static function write_data(&$data, $start)
	{
		$r = 0;
		if (is_array($start) && is_array($data))
		{
			$i = 0;
			$c = sizeof($start);
			for (; $i < $c; $i++)
			{
				if (isset($data[$i]) && isset($start[$i])) $r += shmop_write(self::$shm_data_id, $data[$i], $start[$i]);
			}
		}
		else $r = shmop_write(self::$shm_data_id, $data, $start);
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
	public static function save($k, $v, $ttl = 259200, $tags = NULL)
	{
		if (!self::$ini) self::ini();
		if (!self::$ini) return false;
		if (empty($k) || $v==NULL) return false;
		$auto_unlocker = NULL;
		if (!self::$mutex->get_access_write($auto_unlocker)) return false;
		$map = self::$mem_object->read('map');
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
				$start = self::find_free_space($map, $size);
				if ($start===false) return false;
			}
			else
			{
				if ($size <= ($map[$k][self::map_key_fin]-$map[$k][self::map_key_start])) $start = $map[$k][self::map_key_start];
				else
				{
					self::del_old(array($k));
					$map = self::$mem_object->read('map');
					$start = self::find_free_space($map, $size);
					if ($start===false) return false;
				}
			}
		}
		$r = self::write_data($v, $start);
		if ($r===false) return false;
		$set_ttl = 0;
		if ($ttl > 0 && $ttl < 2592000) $set_ttl = time()+$ttl;
		$map[$k] = array(self::map_key_start => $start, self::map_key_fin => ($start+$size), self::map_key_ttl => $set_ttl, self::map_key_serialized => $data_serialized);
		$r = self::$mem_object->save('map', $map);
		if ($r===false) return false;
		if (!empty($tags) && is_array($tags))
		{
			$mem_tags = self::$mem_object->read('tags');
			foreach ($tags as $tag)
			{
				if (empty($mem_tags[$tag]) || !in_array($k, $mem_tags[$tag])) $mem_tags[$tag][] = $k;
			}
			self::$mem_object->save('tags', $mem_tags);
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
	private static function find_free_space(array $map, $size)
	{
		$c = count($map);
		if ($c < 1) return 0;
		$_end = self::$max_size;
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
	 *
	 * @param mixed $a
	 * @param mixed $b
	 * @return mixed
	 */
	public static function sort_map($a, $b)
	{
		if ($a[self::map_key_start]==$b[self::map_key_start]) return 0;
		if ($a[self::map_key_start] < $b[self::map_key_start]) return -1;
		else return 1;
	}

	/**
	 * Read data from memory storage
	 *
	 * @param mixed $k (string or array of string keys)
	 * @param mixed $ttl_left = (time() - ttl) of key. Use to exclude dog-pile effect, with lock/unlock_key methods.
	 * @return mixed
	 */
	public static function read($k = NULL, &$ttl_left = -1)
	{
		if (!self::$ini) self::ini();
		if (!self::$ini) return false;
		$auto_unlocker = NULL;
		if (!self::$mutex->get_access_read($auto_unlocker)) return false;
		$map = self::$mem_object->read('map');
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
			if (!empty($todelete)) self::del($todelete);
			$data = self::read_data($from_points, $to_points, $k);
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
					self::del($k);
					return NULL;
				}
				else
				{
					$ttl_left = time()-$map[$k][self::map_key_ttl];
				}
			}
			$from = $map[$k][self::map_key_start];
			$to = $map[$k][self::map_key_fin];
			$data = self::read_data($from, $to);
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
	private static function read_data($from, $to, Array $keys = NULL)
	{
		if (!is_array($from) && is_array($to) && !empty($keys))
		{
			$i = 0;
			$c = sizeof($from);
			for (; $i < $c; $i++)
			{
				if (isset($from[$i]) && isset($to[$i]) && isset($keys[$i])) $data[$keys[$i]] = shmop_read(self::$shm_data_id, $from[$i], ($to[$i]-$from[$i]));
			}
		}
		else $data = shmop_read(self::$shm_data_id, $from, ($to-$from));
		return $data;
	}

	/**
	 * Delete key or array of keys from storage (from map)
	 *
	 * @param mixed $k
	 * @return boolean
	 */
	public static function del($k)
	{
		if (!self::$ini) self::ini();
		if (!self::$ini) return false;
		if ($k==NULL || $k=='') return false;
		$auto_unlocker = NULL;
		if (!self::$mutex->get_access_write($auto_unlocker)) return false;
		$map = self::$mem_object->read('map');
		if (is_array($k))
		{
			foreach ($k as $key) unset($map[$key]);
		}
		else unset($map[$k]);
		$r = self::$mem_object->save('map', $map);
		if ($r===false) return false;
		return true;
	}

	/**
	 * Delete old (by ttl) variables from storage (map)
	 *
	 * @return boolean
	 */
	public static function del_old(Array $with_keys = NULL)
	{
		if (!self::$ini) self::ini();
		if (!self::$ini) return false;
		$auto_unlocker = NULL;
		if (!self::$mutex->get_access_write($auto_unlocker)) return false;
		$r = 0;
		$map = self::$mem_object->read('map');
		$todel = array();
		foreach ($map as $k => &$v)
		{
			if (!empty($v[self::map_key_ttl]) && $v[self::map_key_ttl] < time()) $todel[] = $k;
		}
		if (!empty($with_keys)) $todel = array_merge($todel, $with_keys);
		if (!empty($todel)) $r = self::del($todel);
		return $r;
	}

	/**
	 * Delete keys by tags
	 *
	 * @param array|string $tag - tag or array of tags
	 * @return boolean
	 */
	public static function del_by_tags($tag)
	{
		if (!self::$ini) self::ini();
		if (!self::$ini) return false;
		if (empty($tag)) return false;
		$auto_unlocker = NULL;
		if (!self::$mutex->get_access_write($auto_unlocker)) return false;
		$mem_tags = self::$mem_object->read('tags');
		if (!is_array($tag)) $tag = array($tag);
		$todel = array();
		foreach ($tag as $t)
		{
			if (!empty($mem_tags[$t])) $todel = array_merge($todel, $mem_tags[$t]);
		}
		$r = self::del($todel);
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
	public static function select($params, $get_array = false)
	{
		if (!self::$ini) self::ini();
		if (!self::$ini) return false;
		$auto_unlocker = NULL;
		if (!self::$mutex->get_access_read($auto_unlocker)) return false;
		$map = self::$mem_object->read('map');
		$arr = array();
		foreach ($map as $key => &$zs)
		{
			if (!$zs[self::map_key_serialized]) continue;
			$s = self::read($key);
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
	public static function select_fx($fx, $get_array = false)
	{
		if (!self::$ini) self::ini();
		if (!self::$ini) return false;
		$auto_unlocker = NULL;
		if (!self::$mutex->get_access_read($auto_unlocker)) return false;
		$map = self::$mem_object->read('map');
		$arr = array();
		foreach ($map as $index => &$zs)
		{
			if (!$zs[self::map_key_serialized]) continue;
			$s = self::read($index);
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
	public static function increment($key, $by_value = 1)
	{
		if (!self::$ini) self::ini();
		if (!self::$ini) return false;
		if (empty($key)) return false;
		$auto_unlocker = NULL;
		if (!self::$mutex->get_access_write($auto_unlocker)) return false;
		$map = self::$mem_object->read('map');
		if (!array_key_exists($key, $map))
		{
			return self::save($key, $by_value);
		}
		$value = self::read($key);
		if (is_numeric($value) && is_numeric($by_value)) $value += $by_value;
		else $value .= $by_value;
		self::save($key, $value);
		return true;
	}

	public static function get_stat()
	{
		if (!self::$ini) self::ini();
		if (!self::$ini) return false;
		$stat = array();
		$map = self::$mem_object->read('map');
		$size = 0;
		if (!empty($map)) foreach ($map as $v) $size += ($v[self::map_key_fin]-$v[self::map_key_start]);
		$stat['size'] = $size;
		$q_read = msg_get_queue(self::$mutex->read_q_key);
		if (!empty($q_read))
		{
			$q_stat = msg_stat_queue($q_read);
			$stat['readers'] = $q_stat['msg_qnum'];
			$stat['readers_qid'] = self::$mutex->read_q_key;
		}
		$q_writers = msg_get_queue(self::$mutex->write_q_key);
		if (!empty($q_writers))
		{
			$q_stat = msg_stat_queue($q_writers);
			$stat['writers'] = $q_stat['msg_qnum'];
			$stat['writers_qid'] = self::$mutex->write_q_key;
		}
		$stat['shm_data_key'] = self::$shm_data_key;
		$stat['shm_data_id'] = self::$shm_data_id;
		$stat['shm_map_id'] = self::$mem_object->id;
		return $stat;
	}

	/**
	 * Initialize (constructor)
	 *
	 * @param string $id
	 * @param integer $size
	 * @param integer $maxsize
	 * @return boolean
	 */
	public static function ini($id = '', $size = '', $maxsize = '' /*,$prefix=''*/)
	{
		if (self::$ini==true) return true;
		if (is_object(self::$mem_object)) return true;
		if (!empty($id)) self::$id = $id;
		if (!empty($size)) self::$shmsize = $size;
		if (!empty($maxsize)) self::$max_size = $maxsize;
		//if (!empty($prefix) && is_scalar($prefix)) self::$prefix=$prefix;
		//Create mutex "multiple read, one write"
		self::$mutex = new MultiAccess(self::$id);
		//Create shmop to store data
		self::$shm_data_key = ftok(self::$id, 'D'); //D - Data. But I still love my son Nikita ;)
		self::$shm_data_id = @shmop_open(self::$shm_data_key, "w", 0, 0);
		if (!self::$shm_data_id)
		{
			self::$shm_data_id = @shmop_open(self::$shm_data_key, "a", 0, 0);
			if (self::$shm_data_id!==false) self::$readonly = true;
		}
		//if memory not yet exists - lets create
		if (!self::$shm_data_id) self::$shm_data_id = shmop_open(self::$shm_data_key, "n", 0777, self::$max_size);
		if (!self::$shm_data_id) return false;
		//Create mem-object to store map
		$map_id_key = ftok(self::$id, 'h')+12;
		self::$mem_object = new mem($map_id_key, self::$shmsize, self::$max_size);
		if (is_object(self::$mem_object)) self::$ini = true;
		else return false;
		return self::$ini;
	}

	/**
	 * Get exclusive mutex for key. Key will be still accessible to read and write, but
	 * another process can exclude dog-pile effect, if before updating the key he will try to get this mutex.
	 * Example:
	 * Process 1 reads key simultaneously with Process 2.
	 * Value of this key are too old, so Process 1 going to refresh it. Simultaneously with Process 2.
	 * But both of them trying to lock_key, and Process 1 only will refresh value of key (taking it from database, e.g.),
	 * and Process 2 can decide, what he want to do - use old value and not spent time to database, or something else.
	 * @see demo.php
	 * @static
	 * @param mixed $key
	 * @param mixed $auto_unlocker_variable - pass empty, just declared variable
	 */
	public static function lock_key($key, &$auto_unlocker_variable)
	{
		$r = self::$mem_object->add(self::lock_key_prefix.$key, 1);
		if (!$r) return false;
		$auto_unlocker_variable = new Key_AutoUnlocker(array(__CLASS__, 'unlock_key'));
		$auto_unlocker_variable->key = $key;
		return true;
	}

	/**
	 * Unlock key, locked by method 'lock_key'
	 * @static
	 * @param Key_AutoUnlocker $auto_unlocker
	 * @return bool
	 */
	public static function unlock_key(Key_AutoUnlocker $auto_unlocker)
	{
		if (empty($auto_unlocker->key)) return false;
		$auto_unlocker->revoke();
		return self::$mem_object->del(self::lock_key_prefix.$auto_unlocker->key);
	}
}
