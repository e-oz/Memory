<?php
namespace Jamm\Memory;
/**
 * RedisServer allows you to work with Redis storage in PHP
 * Redis version compatibility: 2.6.9 (and below)
 * You can send custom command using send_command() method.
 */
class RedisServer implements IRedisServer
{
	protected $connection;
	private $host = 'localhost';
	private $port = 6379;
	private $repeat_reconnected = false;

	public function __construct($host = 'localhost', $port = '6379')
	{
		$this->host = $host;
		$this->port = $port;
	}

	public function connect($host, $port)
	{
		if (!empty($this->connection))
		{
			fclose($this->connection);
			$this->connection = NULL;
		}
		$socket = fsockopen($host, $port, $errno, $errstr);
		if (!$socket)
		{
			$this->reportError('Connection error: '.$errno.':'.$errstr);
			return false;
		}
		$this->connection = $socket;
		return $socket;
	}

	protected function reportError($msg)
	{
		trigger_error($msg, E_USER_WARNING);
	}

	/**
	 * Execute send_command and return the result
	 * Each entity of the send_command should be passed as argument
	 * Example:
	 *  send_command('set','key','example value');
	 * or:
	 *  send_command('multi');
	 *  send_command('config','ResetStat'); // if command contain 2 words, they should be separated
	 *  send_command('set','a', serialize($arr));
	 *  send_command('set','b', 1);
	 *  send_command('execute');
	 * @return array|bool|int|null|string
	 */
	public function send_command()
	{
		return $this->_send(func_get_args());
	}

	protected function _send($args)
	{
		if (empty($this->connection))
		{
			if (!$this->connect($this->host, $this->port))
			{
				return false;
			}
		}
		$command = '*'.count($args)."\r\n";
		foreach ($args as $arg) $command .= "$".strlen($arg)."\r\n".$arg."\r\n";
		$w = fwrite($this->connection, $command);
		if (!$w)
		{
			//if connection was lost
			$this->connect($this->host, $this->port);
			if (!fwrite($this->connection, $command))
			{
				$this->reportError('command was not sent');
				return false;
			}
		}
		$answer = $this->_read_reply();
		if ($answer===false && $this->repeat_reconnected)
		{
			if (fwrite($this->connection, $command))
			{
				$answer = $this->_read_reply();
			}
			$this->repeat_reconnected = false;
		}
		return $answer;
	}

	/* If some command is not wrapped... */
	public function __call($name, $args)
	{
		$command = trim(str_replace('_', ' ', $name, $replaced));
		if ($replaced > 0)
		{
			$commands = explode(' ', $command);
			$args     = array_merge($commands, $args);
		}
		else
		{
			array_unshift($args, $command);
		}
		return $this->_send($args);
	}

	protected function _read_reply()
	{
		$server_reply = fgets($this->connection);
		if ($server_reply===false)
		{
			if (!$this->connect($this->host, $this->port))
			{
				return false;
			}
			else
			{
				$server_reply = fgets($this->connection);
				if (empty($server_reply))
				{
					$this->repeat_reconnected = true;
					return false;
				}
			}
		}
		$reply    = trim($server_reply);
		$response = null;
		/**
		 * Thanks to Justin Poliey for original code of parsing the answer
		 * https://github.com/jdp
		 * Error was fixed there: https://github.com/jamm/redisent
		 */
		switch ($reply[0])
		{
			/* Error reply */
			case '-':
				$this->reportError('error: '.$reply);
				return false;
			/* Inline reply */
			case '+':
				return substr($reply, 1);
			/* Bulk reply */
			case '$':
				if ($reply=='$-1') return null;
				$response = null;
				$size     = intval(substr($reply, 1));
				if ($size > 0)
				{
					$response = stream_get_contents($this->connection, $size);
				}
				fread($this->connection, 2); /* discard crlf */
				break;
			/* Multi-bulk reply */
			case '*':
				$count = substr($reply, 1);
				if ($count=='-1') return null;
				$response = array();
				for ($i = 0; $i < $count; $i++)
				{
					$response[] = $this->_read_reply();
				}
				break;
			/* Integer reply */
			case ':':
				return intval(substr($reply, 1));
				break;
			default:
				$this->reportError('Non-protocol answer: '.print_r($server_reply, 1));
				return false;
		}
		return $response;
	}

	public function Get($key)
	{
		return $this->_send(array('get', $key));
	}

	public function Set($key, $value)
	{
		return $this->_send(array('set', $key, $value));
	}

	public function SetEx($key, $seconds, $value)
	{
		return $this->_send(array('setex', $key, $seconds, $value));
	}

	public function Keys($pattern)
	{
		return $this->_send(array('keys', $pattern));
	}

	public function Multi()
	{
		return $this->_send(array('multi'));
	}

	public function sAdd($key, $member)
	{
		if (!is_array($member)) $member = func_get_args();
		else array_unshift($member, $key);
		return $this->__call('sadd', $member);
	}

	public function sMembers($key)
	{
		return $this->_send(array('smembers', $key));
	}

	public function hSet($key, $field, $value)
	{
		return $this->_send(array('hset', $key, $field, $value));
	}

	public function hGetAll($key)
	{
		$arr = $this->_send(array('hgetall', $key));
		$c   = count($arr);
		$r   = array();
		for ($i = 0; $i < $c; $i += 2)
		{
			$r[$arr[$i]] = $arr[$i+1];
		}
		return $r;
	}

	public function FlushDB()
	{
		return $this->_send(array('flushdb'));
	}

	public function Info()
	{
		return $this->_send(array('info'));
	}

	/** Close connection */
	public function __destruct()
	{
		if (!empty($this->connection)) fclose($this->connection);
	}

	public function SetNX($key, $value)
	{
		return $this->_send(array('setnx', $key, $value));
	}

	public function Watch($key)
	{
		$args = func_get_args();
		array_unshift($args, 'watch');
		return $this->_send($args);
	}

	public function Exec()
	{
		return $this->_send(array('exec'));
	}

	public function Discard()
	{
		return $this->_send(array('discard'));
	}

	public function sIsMember($key, $member)
	{
		return $this->_send(array('sismember', $key, $member));
	}

	public function sRem($key, $member)
	{
		if (!is_array($member)) $member = func_get_args();
		else array_unshift($member, $key);
		return $this->__call('srem', $member);
	}

	public function Expire($key, $seconds)
	{
		return $this->_send(array('expire', $key, $seconds));
	}

	public function TTL($key)
	{
		return $this->_send(array('ttl', $key));
	}

	public function Del($key)
	{
		if (!is_array($key)) $key = func_get_args();
		return $this->__call('del', $key);
	}

	public function IncrBy($key, $increment)
	{
		return $this->_send(array('incrby', $key, $increment));
	}

	public function Append($key, $value)
	{
		return $this->_send(array('append', $key, $value));
	}

	public function Auth($password)
	{
		return $this->_send(array('Auth', $password));
	}

	public function bgRewriteAOF()
	{
		return $this->_send(array('bgRewriteAOF'));
	}

	public function bgSave()
	{
		return $this->_send(array('bgSave'));
	}

	public function BLPop($key, $timeout)
	{
		if (!is_array($key)) $key = func_get_args();
		else array_push($key, $timeout);
		return $this->__call('BLPop', $key);
	}

	public function BRPop($key, $timeout)
	{
		if (!is_array($key)) $key = func_get_args();
		else array_push($key, $timeout);
		return $this->__call('BRPop', $key);
	}

	public function BRPopLPush($source, $destination, $timeout)
	{
		return $this->_send(array('BRPopLPush', $source, $destination, $timeout));
	}

	public function Config_Get($parameter)
	{
		return $this->_send(array('CONFIG', 'GET', $parameter));
	}

	public function Config_Set($parameter, $value)
	{
		return $this->_send(array('CONFIG', 'SET', $parameter, $value));
	}

	public function Config_ResetStat()
	{
		//return $this->_send(array('CONFIG', 'RESETSTAT'));
		return $this->__call('CONFIG_RESETSTAT', []);
	}

	public function DBsize()
	{
		return $this->_send(array('dbsize'));
	}

	public function Decr($key)
	{
		return $this->_send(array('decr', $key));
	}

	public function DecrBy($key, $decrement)
	{
		return $this->_send(array('DecrBy', $key, $decrement));
	}

	public function Exists($key)
	{
		return $this->_send(array('Exists', $key));
	}

	public function Expireat($key, $timestamp)
	{
		return $this->_send(array('Expireat', $key, $timestamp));
	}

	public function FlushAll()
	{
		return $this->_send(array('flushall'));
	}

	public function GetBit($key, $offset)
	{
		return $this->_send(array('GetBit', $key, $offset));
	}

	public function GetRange($key, $start, $end)
	{
		return $this->_send(array('getrange', $key, $start, $end));
	}

	public function GetSet($key, $value)
	{
		return $this->_send(array('GetSet', $key, $value));
	}

	public function hDel($key, $field)
	{
		if (!is_array($field)) $field = func_get_args();
		else array_unshift($field, $key);
		return $this->__call('hdel', $field);
	}

	public function hExists($key, $field)
	{
		return $this->_send(array('hExists', $key, $field));
	}

	public function hGet($key, $field)
	{
		return $this->_send(array('hGet', $key, $field));
	}

	public function hIncrBy($key, $field, $increment)
	{
		return $this->_send(array('hIncrBy', $key, $field, $increment));
	}

	public function hKeys($key)
	{
		return $this->_send(array('hKeys', $key));
	}

	public function hLen($key)
	{
		return $this->_send(array('hLen', $key));
	}

	public function hMGet($key, array $fields)
	{
		array_unshift($fields, $key);
		return $this->__call('hMGet', $fields);
	}

	public function hMSet($key, $fields)
	{
		$args[] = $key;
		foreach ($fields as $field => $value)
		{
			$args[] = $field;
			$args[] = $value;
		}
		return $this->__call('hMSet', $args);
	}

	public function hSetNX($key, $field, $value)
	{
		return $this->_send(array('hSetNX', $key, $field, $value));
	}

	public function hVals($key)
	{
		return $this->_send(array('hVals', $key));
	}

	public function Incr($key)
	{
		return $this->_send(array('Incr', $key));
	}

	public function LIndex($key, $index)
	{
		return $this->_send(array('LIndex', $key, $index));
	}

	public function LInsert($key, $after = true, $pivot, $value)
	{
		if ($after) $position = self::Position_AFTER;
		else $position = self::Position_BEFORE;
		return $this->_send(array('LInsert', $key, $position, $pivot, $value));
	}

	public function LLen($key)
	{
		return $this->_send(array('LLen', $key));
	}

	public function LPop($key)
	{
		return $this->_send(array('LPop', $key));
	}

	public function LPush($key, $value)
	{
		if (!is_array($value)) $value = func_get_args();
		else array_unshift($value, $key);
		return $this->__call('lpush', $value);
	}

	public function LPushX($key, $value)
	{
		return $this->_send(array('LPushX', $key, $value));
	}

	public function LRange($key, $start, $stop)
	{
		return $this->_send(array('LRange', $key, $start, $stop));
	}

	public function LRem($key, $count, $value)
	{
		return $this->_send(array('LRem', $key, $count, $value));
	}

	public function LSet($key, $index, $value)
	{
		return $this->_send(array('LSet', $key, $index, $value));
	}

	public function LTrim($key, $start, $stop)
	{
		return $this->_send(array('LTrim', $key, $start, $stop));
	}

	public function MGet($key)
	{
		if (!is_array($key)) $key = func_get_args();
		return $this->__call('MGet', $key);
	}

	public function Move($key, $db)
	{
		return $this->_send(array('Move', $key, $db));
	}

	public function MSet(array $keys)
	{
		$q = array();
		foreach ($keys as $k => $v)
		{
			$q[] = $k;
			$q[] = $v;
		}
		return $this->__call('MSet', $q);
	}

	public function MSetNX(array $keys)
	{
		$q = array();
		foreach ($keys as $k => $v)
		{
			$q[] = $k;
			$q[] = $v;
		}
		return $this->__call('MSetNX', $q);
	}

	public function Persist($key)
	{
		return $this->_send(array('Persist', $key));
	}

	public function PSubscribe($pattern)
	{
		return $this->_send(array('PSubscribe', $pattern));
	}

	public function Publish($channel, $message)
	{
		return $this->_send(array('Publish', $channel, $message));
	}

	public function PUnsubscribe($pattern = null)
	{
		if (!empty($pattern))
		{
			if (!is_array($pattern)) $pattern = array($pattern);
			return $this->__call('PUnsubscribe', $pattern);
		}
		else return $this->_send(array('PUnsubscribe'));
	}

	public function Quit()
	{
		return $this->_send(array('Quit'));
	}

	public function Rename($key, $newkey)
	{
		return $this->_send(array('Rename', $key, $newkey));
	}

	public function RenameNX($key, $newkey)
	{
		return $this->_send(array('RenameNX', $key, $newkey));
	}

	public function RPop($key)
	{
		return $this->_send(array('RPop', $key));
	}

	public function RPopLPush($source, $destination)
	{
		return $this->_send(array('RPopLPush', $source, $destination));
	}

	public function RPush($key, $value)
	{
		if (!is_array($value)) $value = func_get_args();
		else array_unshift($value, $key);
		return $this->__call('rpush', $value);
	}

	public function RPushX($key, $value)
	{
		return $this->_send(array('RPushX', $key, $value));
	}

	public function sCard($key)
	{
		return $this->_send(array('sCard', $key));
	}

	public function sDiff($key)
	{
		if (!is_array($key)) $key = func_get_args();
		return $this->__call('sDiff', $key);
	}

	public function sDiffStore($destination, $key)
	{
		if (!is_array($key)) $key = func_get_args();
		else array_unshift($key, $destination);
		return $this->__call('sDiffStore', $key);
	}

	public function Select($index)
	{
		return $this->_send(array('Select', $index));
	}

	public function SetBit($key, $offset, $value)
	{
		return $this->_send(array('SetBit', $key, $offset, $value));
	}

	public function SetRange($key, $offset, $value)
	{
		return $this->_send(array('SetRange', $key, $offset, $value));
	}

	public function sInter($key)
	{
		if (!is_array($key)) $key = func_get_args();
		return $this->__call('sInter', $key);
	}

	public function sInterStore($destination, $key)
	{
		if (is_array($key)) array_unshift($key, $destination);
		else $key = func_get_args();
		return $this->__call('sInterStore', $key);
	}

	public function SlaveOf($host, $port)
	{
		return $this->_send(array('SlaveOf', $host, $port));
	}

	public function sMove($source, $destination, $member)
	{
		return $this->_send(array('sMove', $source, $destination, $member));
	}

	public function Sort($key, $sort_rule)
	{
		return $this->_send(array('Sort', $key, $sort_rule));
	}

	public function StrLen($key)
	{
		return $this->_send(array('StrLen', $key));
	}

	public function Subscribe($channel)
	{
		if (!is_array($channel)) $channel = func_get_args();
		return $this->__call('Subscribe', $channel);
	}

	public function sUnion($key)
	{
		if (!is_array($key)) $key = func_get_args();
		return $this->__call('sUnion', $key);
	}

	public function sUnionStore($destination, $key)
	{
		if (!is_array($key)) $key = func_get_args();
		else array_unshift($key, $destination);
		return $this->__call('sUnionStore', $key);
	}

	public function Type($key)
	{
		return $this->_send(array('Type', $key));
	}

	public function Unsubscribe($channel = '')
	{
		$args = func_get_args();
		if (empty($args)) return $this->_send(array('Unsubscribe'));
		else
		{
			if (is_array($channel)) return $this->__call('Unsubscribe', $channel);
			else return $this->__call('Unsubscribe', $args);
		}
	}

	public function Unwatch()
	{
		return $this->_send(array('Unwatch'));
	}

	public function zAdd($key, $score, $member = NULL)
	{
		if (!is_array($score)) $values = func_get_args();
		else
		{
			foreach ($score as $score_value => $member)
			{
				$values[] = $score_value;
				$values[] = $member;
			}
			array_unshift($values, $key);
		}
		return $this->__call('zadd', $values);
	}

	public function zCard($key)
	{
		return $this->_send(array('zCard', $key));
	}

	public function zCount($key, $min, $max)
	{
		return $this->_send(array('zCount', $key, $min, $max));
	}

	public function zIncrBy($key, $increment, $member)
	{
		return $this->_send(array('zIncrBy', $key, $increment, $member));
	}

	public function zInterStore($destination, array $keys, array $weights = null, $aggregate = null)
	{
		$destination = array($destination, count($keys));
		$destination = array_merge($destination, $keys);
		if (!empty($weights))
		{
			$destination[] = 'WEIGHTS';
			$destination   = array_merge($destination, $weights);
		}
		if (!empty($aggregate))
		{
			$destination[] = 'AGGREGATE';
			$destination[] = $aggregate;
		}
		return $this->__call('zInterStore', $destination);
	}

	public function zRange($key, $start, $stop, $withscores = false)
	{
		if ($withscores) return $this->_send(array('zRange', $key, $start, $stop, self::WITHSCORES));
		else return $this->_send(array('zRange', $key, $start, $stop));
	}

	public function zRangeByScore($key, $min, $max, $withscores = false, array $limit = null)
	{
		$args = array($key, $min, $max);
		if ($withscores) $args[] = self::WITHSCORES;
		if (!empty($limit))
		{
			$args[] = 'LIMIT';
			$args[] = $limit[0];
			$args[] = $limit[1];
		}
		return $this->__call('zRangeByScore', $args);
	}

	public function zRank($key, $member)
	{
		return $this->_send(array('zRank', $key, $member));
	}

	public function zRem($key, $member)
	{
		if (!is_array($member)) $member = func_get_args();
		else array_unshift($member, $key);
		return $this->__call('zrem', $member);
	}

	public function zRemRangeByRank($key, $start, $stop)
	{
		return $this->_send(array('zRemRangeByRank', $key, $start, $stop));
	}

	public function zRemRangeByScore($key, $min, $max)
	{
		return $this->_send(array('zRemRangeByScore', $key, $min, $max));
	}

	public function zRevRange($key, $start, $stop, $withscores = false)
	{
		if ($withscores) return $this->_send(array('zRevRange', $key, $start, $stop, self::WITHSCORES));
		else return $this->_send(array('zRevRange', $key, $start, $stop));
	}

	public function zRevRangeByScore($key, $max, $min, $withscores = false, array $limit = null)
	{
		$args = array($key, $max, $min);
		if ($withscores) $args[] = self::WITHSCORES;
		if (!empty($limit))
		{
			$args[] = 'LIMIT';
			$args[] = $limit[0];
			$args[] = $limit[1];
		}
		return $this->__call('zRevRangeByScore', $args);
	}

	public function zRevRank($key, $member)
	{
		return $this->_send(array('zRevRank', $key, $member));
	}

	public function zScore($key, $member)
	{
		return $this->_send(array('zScore', $key, $member));
	}

	public function zUnionStore($destination, array $keys, array $weights = null, $aggregate = null)
	{
		$destination = array($destination, count($keys));
		$destination = array_merge($destination, $keys);
		if (!empty($weights))
		{
			$destination[] = 'WEIGHTS';
			$destination   = array_merge($destination, $weights);
		}
		if (!empty($aggregate))
		{
			$destination[] = 'AGGREGATE';
			$destination[] = $aggregate;
		}
		return $this->__call('zUnionStore', $destination);
	}

	/** Internal command used for replication */
	public function SYNC()
	{
		return $this->_send(array('SYNC'));
	}

	/**
	 * Get a random member from a set
	 * @param string $key
	 * @param int $count
	 * @return string
	 */
	public function SRANDMEMBER($key, $count = 1)
	{
		return $this->_send(array('SRANDMEMBER', $key, $count));
	}

	/**
	 * Remove and return a random member from a set
	 * @param string $key
	 * @return string
	 */
	public function SPOP($key)
	{
		return $this->_send(array('SPOP', $key));
	}

	/**
	 * Manages the Redis slow queries log
	 * @param string $subcommand
	 * @param string $argument
	 * @return mixed
	 */
	public function SLOWLOG($subcommand, $argument = '')
	{
		return $this->_send(array('SLOWLOG', $subcommand, $argument));
	}

	/**
	 * Synchronously save the dataset to disk and then shut down the server
	 * One of modifiers can be turned on:
	 * @param boolean $save   will force a DB saving operation even if no save points are configured.
	 * @param boolean $nosave will prevent a DB saving operation even if one or more save points are configured.
	 * @return bool
	 */
	public function SHUTDOWN($save = false, $nosave = false)
	{
		if ($save)
		{
			return $this->_send(array('SHUTDOWN', 'SAVE'));
		}
		elseif ($nosave)
		{
			return $this->_send(array('SHUTDOWN', 'NOSAVE'));
		}
		return $this->_send(array('SHUTDOWN'));
	}

	/** Synchronously save the dataset to disk */
	public function SAVE()
	{
		return $this->_send(array('SAVE'));
	}

	/** Return a random key from the keyspace */
	public function RANDOMKEY()
	{
		return $this->_send(array('RANDOMKEY'));
	}

	/**
	 * Inspect the internals of Redis objects
	 * @param string $subcommand
	 * @param array $arguments
	 * @return mixed
	 */
	public function OBJECT($subcommand, $arguments = array())
	{
		array_unshift($arguments, $subcommand);
		return $this->__call('OBJECT', $arguments);
	}

	/** Listen for all requests received by the server in real time */
	public function MONITOR()
	{
		return $this->_send(array('MONITOR'));
	}

	/** Get the UNIX time stamp of the last successful save to disk Ping the server */
	public function LASTSAVE()
	{
		return $this->_send(array('LASTSAVE'));
	}

	/** Ping the server */
	public function  PING()
	{
		return $this->_send(array('PING'));
	}

	/**
	 * Echo the given string
	 * @param string $message
	 * @return string
	 */
	public function ECHO_($message)
	{
		return $this->_send(array('ECHO', $message));
	}

	/** Make the server crash */
	public function DEBUG_SEGFAULT()
	{
		return $this->_send(array('DEBUG', 'SEGFAULT'));
	}

	/**
	 * Get debugging information about a key
	 * @param string $key
	 * @return mixed
	 */
	public function DEBUG_OBJECT($key)
	{
		return $this->_send(array('DEBUG', 'OBJECT', $key));
	}

	/**
	 * Count the number of set bits (population counting) in a string.
	 * By default all the bytes contained in the string are examined.
	 * It is possible to specify the counting operation only in an interval passing the additional arguments start and end.
	 * @param string $key
	 * @param int $start
	 * @param int $end
	 * @return int
	 */
	public function BITCOUNT($key, $start = 0, $end = 0)
	{
		if ($start > 0)
		{
			return $this->_send(array('BITCOUNT', $key, $start, $end));
		}
		return $this->_send(array('BITCOUNT', $key));
	}

	/**
	 * Perform a bitwise operation between multiple keys (containing string values) and store the result in the destination key.
	 * The BITOP command supports four bitwise operations: AND, OR, XOR and NOT, thus the valid forms to call the command are:
	 * BITOP AND destkey srckey1 srckey2 srckey3 ... srckeyN
	 * BITOP OR destkey srckey1 srckey2 srckey3 ... srckeyN
	 * BITOP XOR destkey srckey1 srckey2 srckey3 ... srckeyN
	 * BITOP NOT destkey srckey
	 * As you can see NOT is special as it only takes an input key, because it performs inversion of bits so it only makes sense as an unary operator.
	 * The result of the operation is always stored at destkey.
	 * @param string $operation
	 * @param string $destkey
	 * @param string $key
	 * @return integer
	 * @usage
	 * BITOP(operation, destkey, key1 [, key2...])
	 */
	public function BITOP($operation, $destkey, $key)
	{
		return $this->_send(array('BITOP', func_get_args()));
	}

	/**
	 * The CLIENT KILL command closes a given client connection identified by ip:port.
	 * The ip:port should match a line returned by the CLIENT LIST command.
	 * @param $ip
	 * @param $port
	 * @return boolean
	 */
	public function CLIENT_KILL($ip, $port)
	{
		return $this->_send(array('CLIENT', 'KILL', $ip.':'.$port));
	}

	/** Get the list of client connections */
	public function CLIENT_LIST()
	{
		return $this->_send(array('CLIENT', 'LIST'));
	}

	/** Get the current connection name */
	public function CLIENT_GETNAME()
	{
		return $this->_send(array('CLIENT', 'GETNAME'));
	}

	/**
	 * Set the current connection name
	 * @param string $connection_name
	 * @return boolean
	 */
	public function CLIENT_SETNAME($connection_name)
	{
		return $this->_send(array('CLIENT', 'SETNAME', $connection_name));
	}

	/**
	 * Serialize the value stored at key in a Redis-specific format and return it to the user.
	 * The returned value can be synthesized back into a Redis key using the RESTORE command.
	 * @param string $key
	 * @return string
	 */
	public function DUMP($key)
	{
		return $this->_send(array('DUMP', $key));
	}

	/**
	 * Execute a Lua script server side
	 * @param string $script
	 * @param array $keys
	 * @param array $args
	 * @return mixed
	 */
	public function EVAL_($script, array $keys, array $args)
	{
		$params = array('EVAL', $script, count($keys));
		$params = array_merge($params, $keys);
		$params = array_merge($params, $args);
		return $this->_send($params);
	}

	/**
	 * Execute a Lua script server side
	 * @param $sha1
	 * @param array $keys
	 * @param array $args
	 * @return mixed
	 */
	public function EVALSHA($sha1, array $keys, array $args)
	{
		$params = array('EVALSHA', $sha1, count($keys));
		$params = array_merge($params, $keys);
		$params = array_merge($params, $args);
		return $this->_send($params);
	}

	/**
	 * Increment the specified field of an hash stored at key, and representing a floating point number, by the specified increment.
	 * If the field does not exist, it is set to 0 before performing the operation.
	 * @param string $key
	 * @param string $field
	 * @param float $increment
	 * @return float the value of field after the increment
	 */
	public function HINCRBYFLOAT($key, $field, $increment)
	{
		return $this->_send(array('HINCRBYFLOAT', $key, $field, $increment));
	}

	/**
	 * Increment the string representing a floating point number stored at key by the specified increment.
	 * If the key does not exist, it is set to 0 before performing the operation.
	 * @param string $key
	 * @param float $increment
	 * @return float the value of key after the increment
	 */
	public function INCRBYFLOAT($key, $increment)
	{
		return $this->_send(array('INCRBYFLOAT', $key, $increment));
	}

	/**
	 * Atomically transfer a key from a Redis instance to another one.
	 * On success the key is deleted from the original instance and is guaranteed to exist in the target instance.
	 * The command is atomic and blocks the two instances for the time required to transfer the key, at any given time the key will appear to exist in a given instance or in the other instance, unless a timeout error occurs.
	 * @param string $host
	 * @param string $port
	 * @param string $key
	 * @param integer $destination_db
	 * @param integer $timeout
	 * @return boolean
	 */
	public function MIGRATE($host, $port, $key, $destination_db, $timeout)
	{
		return $this->_send(array('MIGRATE', $host, $port, $key, $destination_db, $timeout));
	}

	/**
	 * Set a key's time to live in milliseconds
	 * @param string $key
	 * @param integer $milliseconds
	 * @return integer 1 if the timeout was set, 0 if key does not exist or the timeout could not be set.
	 */
	public function PEXPIRE($key, $milliseconds)
	{
		return $this->_send(array('PEXPIRE', $key, $milliseconds));
	}

	/**
	 * Set the expiration for a key as a UNIX timestamp specified in milliseconds
	 * @param string $key
	 * @param int $milliseconds_timestamp the Unix time at which the key will expire
	 * @return integer 1 if the timeout was set, 0 if key does not exist or the timeout could not be set.
	 */
	public function PEXPIREAT($key, $milliseconds_timestamp)
	{
		return $this->_send(array('PEXPIREAT', $key, $milliseconds_timestamp));
	}

	/**
	 * Set the value and expiration in milliseconds of a key
	 * @param string $key
	 * @param int $milliseconds
	 * @param string $value
	 * @return boolean
	 */
	public function PSETEX($key, $milliseconds, $value)
	{
		return $this->_send(array('PSETEX', $key, $milliseconds, $value));
	}

	/**
	 * Get the time to live for a key in milliseconds
	 * @param string $key
	 * @return int Time to live in milliseconds or -1 when key does not exist or does not have a timeout.
	 */
	public function PTTL($key)
	{
		return $this->_send(array('PTTL', $key));
	}

	/**
	 * Create a key using the provided serialized value, previously obtained using DUMP.
	 * @param string $key
	 * @param int $ttl If ttl is 0 the key is created without any expire, otherwise the specified expire time (in milliseconds) is set.
	 * @param string $serialized_value
	 * @return boolean
	 */
	public function RESTORE($key, $ttl, $serialized_value)
	{
		return $this->_send(array('RESTORE', $key, $ttl, $serialized_value));
	}

	/**
	 * Check existence of scripts in the script cache.
	 * @param string $script
	 * @return array
	 */
	public function SCRIPT_EXISTS($script)
	{
		return $this->_send(array('SCRIPT', 'EXISTS', $script));
	}

	/** Remove all the scripts from the script cache. */
	public function SCRIPT_FLUSH()
	{
		return $this->_send(array('SCRIPT', 'FLUSH'));
	}

	/** Kills the currently executing Lua script, assuming no write operation was yet performed by the script. */
	public function SCRIPT_KILL()
	{
		return $this->_send(array('SCRIPT', 'KILL'));
	}

	/**
	 * Load a script into the scripts cache, without executing it.
	 * After the specified command is loaded into the script cache it will be callable using EVALSHA with the correct SHA1 digest
	 * of the script, exactly like after the first successful invocation of EVAL.
	 * @param string $script
	 * @return string This command returns the SHA1 digest of the script added into the script cache.
	 */
	public function SCRIPT_LOAD($script)
	{
		return $this->_send(array('SCRIPT', 'LOAD', $script));
	}

	/**
	 * Returns the current server time as a two items lists: a Unix timestamp and the amount of microseconds already elapsed in the current second
	 * @return array
	 */
	public function TIME()
	{
		return $this->_send(array('TIME'));
	}
}
