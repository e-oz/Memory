<?php
namespace Jamm\Memory;

/**
 * RedisServer allows you to work with Redis storage in PHP
 *
 * Almost all methods of this class have same names as commands of Redis,
 * each method is documented by PhpDoc-commentary.
 *
 * This class doesn't require IRedisServer interface, you can just comment out "//implements IRedisServer"
 * Interface exists just for smart code completion in IDE.
 *
 * You can send custom command using send_command() method,
 * and read errors by getLastErr() and getErrLog() methods.
 *
 * All debug-commands declared as magic methods and implemented via __call() method:
 * @method mixed DEBUG_OBJECT($key) Get debugging information about a key
 * @method mixed DEBUG_SEGFAULT() Make the server crash
 * @method string ECHO($message) Echo the given string
 * @method string PING() Ping the server
 * @method int LASTSAVE() Get the UNIX time stamp of the last successful save to disk Ping the server
 * @method mixed MONITOR() Listen for all requests received by the server in real time
 * @method mixed OBJECT($subcommand) Inspect the internals of Redis objects
 * @method mixed RANDOMKEY() Return a random key from the keyspace
 * @method mixed SAVE() Synchronously save the dataset to disk
 * @method mixed SHUTDOWN() Synchronously save the dataset to disk and then shut down the server
 * @method mixed SLOWLOG($subcommand) Manages the Redis slow queries log
 * @method string SPOP(string $key) Remove and return a random member from a set
 * @method string SRANDMEMBER(string $key) Get a random member from a set
 * @method string SYNC() Internal command used for replication
 */
class RedisServer implements IRedisServer
{
	protected $connection;
	protected $last_err;
	protected $err_log;
	private $host = 'localhost';
	private $port = 6379;

	public function __construct($host = 'localhost', $port = '6379')
	{
		$this->host = $host;
		$this->port = $port;
		$this->connect($host, $port);
	}

	public function connect($host, $port)
	{
		if (!empty($this->connection)) fclose($this->connection);
		$this->connection = $socket = fsockopen($host, $port, $errno, $errstr);
		if (!$socket) return $this->ReportError('Connection error: '.$errno.':'.$errstr, __LINE__);
		stream_set_timeout($socket, 2592000);
		return $socket;
	}

	public function getLastErr()
	{
		$t              = $this->last_err;
		$this->last_err = '';
		return $t;
	}

	public function ReportError($msg, $line)
	{
		$this->last_err  = $line.': '.$msg;
		$this->err_log[] = $line.': '.$msg;
		return false;
	}

	public function getErrLog()
	{ return $this->err_log; }

	/**
	 * Execute send_command and return the result
	 * Each entity of the send_command should be passed as argument
	 * Example:
	 *  send_command('set','key','example value');
	 * or:
	 *  send_command('multi');
	 *  send_command('set','a', serialize($arr));
	 *  send_command('set','b', 1);
	 *  send_command('execute');
	 * @return array|bool|int|null|string
	 */
	public function send_command()
	{
		$args = func_get_args();
		return $this->_send($args);
	}

	protected function _send($args)
	{
		$command = '*'.count($args)."\r\n";
		foreach ($args as $arg) $command .= "$".strlen($arg)."\r\n".$arg."\r\n";

		$w = fwrite($this->connection, $command);
		if (!$w)
		{
			//if connection was lost
			$this->connect($this->host, $this->port);
			if (!fwrite($this->connection, $command))
			{
				return $this->ReportError('command was not sent', __LINE__);
			}
		}
		return $this->read_reply();
	}

	/* If some command is not wrapped... */
	public function __call($name, $args)
	{
		array_unshift($args, str_replace('_', ' ', $name));
		return $this->_send($args);
	}

	public function read_reply()
	{
		$reply    = trim(fgets($this->connection));
		$response = null;

		/**
		 * Thanks to Justin Poliey for original code of parsing the answer
		 * https://github.com/jdp
		 * Error was fixed there: https://github.com/jamm/redisent
		 */
		switch (substr($reply, 0, 1))
		{
			/* Error reply */
			case '-':
				return $this->ReportError('error: '.$reply, __LINE__);
				break;
			/* Inline reply */
			case '+':
				return substr(trim($reply), 1);
				break;
			/* Bulk reply */
			case '$':
				$response = null;
				if ($reply=='$-1') return null;
				$read = 0;
				$size = intval(substr($reply, 1));
				$chi  = 0;
				if ($size > 0)
				{
					do
					{
						$chi++;
						$block_size = $size-$read;
						if ($block_size > 1024) $block_size = 1024;
						if ($block_size < 1) break;
						if ($chi > 1000) return $this->ReportError('loooop', __LINE__);
						$response .= fread($this->connection, $block_size);
						$read += $block_size;
					} while ($read < $size);
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
					$response[] = $this->read_reply();
				}
				break;
			/* Integer reply */
			case ':':
				return intval(substr(trim($reply), 1));
				break;
			default:
				return $this->ReportError('unkown answer: '.$reply, __LINE__);
				break;
		}

		return $response;
	}

	public function Get($key)
	{
		return $this->send_command('get', $key);
	}

	public function Set($key, $value)
	{
		return $this->send_command('set', $key, $value);
	}

	public function SetEx($key, $seconds, $value)
	{
		return $this->send_command('setex', $key, $seconds, $value);
	}

	public function Keys($pattern)
	{
		return $this->send_command('keys', $pattern);
	}

	public function Multi()
	{
		return $this->send_command('multi');
	}

	public function sAdd($set, $value)
	{
		if (!is_array($value)) $value = func_get_args();
		else array_unshift($value, $set);
		return $this->__call('sadd', $value);
	}

	public function sMembers($set)
	{
		return $this->send_command('smembers', $set);
	}

	public function hSet($key, $field, $value)
	{
		return $this->send_command('hset', $key, $field, $value);
	}

	public function hGetAll($key)
	{
		$arr = $this->send_command('hgetall', $key);
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
		return $this->send_command('flushdb');
	}

	public function Info()
	{
		return $this->send_command('info');
	}

	/** Close connection */
	public function __destruct()
	{
		if (!empty($this->connection)) fclose($this->connection);
	}

	public function SetNX($key, $value)
	{
		return $this->send_command('setnx', $key, $value);
	}

	public function Watch()
	{
		$args = func_get_args();
		array_unshift($args, 'watch');
		return $this->_send($args);
	}

	public function Exec()
	{
		return $this->send_command('exec');
	}

	public function Discard()
	{
		return $this->send_command('discard');
	}

	public function sIsMember($set, $value)
	{
		return $this->send_command('sismember', $set, $value);
	}

	public function sRem($set, $value)
	{
		if (!is_array($value)) $value = func_get_args();
		else array_unshift($value, $set);
		return $this->__call('srem', $value);
	}

	public function Expire($key, $seconds)
	{
		return $this->send_command('expire', $key, $seconds);
	}

	public function TTL($key)
	{
		return $this->send_command('ttl', $key);
	}

	public function Del($key)
	{
		if (!is_array($key)) $key = func_get_args();
		return $this->__call('del', $key);
	}

	public function IncrBy($key, $increment)
	{
		return $this->send_command('incrby', $key, $increment);
	}

	public function Append($key, $value)
	{
		return $this->send_command('append', $key, $value);
	}

	public function Auth($pasword)
	{
		return $this->send_command('Auth', $pasword);
	}

	public function bgRewriteAOF()
	{
		return $this->send_command('bgRewriteAOF');
	}

	public function bgSave()
	{
		return $this->send_command('bgSave');
	}

	public function BLPop($keys, $timeout)
	{
		if (!is_array($keys)) $keys = func_get_args();
		else array_push($keys, $timeout);
		return $this->__call('BLPop', $keys);
	}

	public function BRPop($keys, $timeout)
	{
		if (!is_array($keys)) $keys = func_get_args();
		else array_push($keys, $timeout);
		return $this->__call('BRPop', $keys);
	}

	public function BRPopLPush($source, $destination, $timeout)
	{
		return $this->send_command('BRPopLPush', $source, $destination, $timeout);
	}

	public function Config_Get($pattern)
	{
		return $this->send_command('CONFIG GET', $pattern);
	}

	public function Config_Set($parameter, $value)
	{
		return $this->send_command('CONFIG SET', $parameter, $value);
	}

	public function Config_ResetStat()
	{
		return $this->send_command('CONFIG RESETSTAT');
	}

	public function DBsize()
	{
		return $this->send_command('DBsize');
	}

	public function Decr($key)
	{
		return $this->send_command('Decr', $key);
	}

	public function DecrBy($key, $decrement)
	{
		return $this->send_command('DecrBy', $key, $decrement);
	}

	public function Exists($key)
	{
		return $this->send_command('Exists', $key);
	}

	public function Expireat($key, $timestamp)
	{
		return $this->send_command('Expireat', $key, $timestamp);
	}

	public function FlushAll()
	{
		return $this->send_command('FlushAll');
	}

	public function GetBit($key, $offset)
	{
		return $this->send_command('GetBit', $key, $offset);
	}

	public function GetRange($key, $start, $end)
	{
		return $this->send_command('GetRange', $key, $start, $end);
	}

	public function GetSet($key, $value)
	{
		return $this->send_command('GetSet', $key, $value);
	}

	public function hDel($key, $field)
	{
		if (!is_array($field)) $field = func_get_args();
		else array_unshift($field, $key);
		return $this->__call('hdel', $field);
	}

	public function hExists($key, $field)
	{
		return $this->send_command('hExists', $key, $field);
	}

	public function hGet($key, $field)
	{
		return $this->send_command('hGet', $key, $field);
	}

	public function hIncrBy($key, $field, $increment)
	{
		return $this->send_command('hIncrBy', $key, $field, $increment);
	}

	public function hKeys($key)
	{
		return $this->send_command('hKeys', $key);
	}

	public function hLen($key)
	{
		return $this->send_command('hLen', $key);
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
		return $this->send_command('hSetNX', $key, $field, $value);
	}

	public function hVals($key)
	{
		return $this->send_command('hVals', $key);
	}

	public function Incr($key)
	{
		return $this->send_command('Incr', $key);
	}

	public function LIndex($key, $index)
	{
		return $this->send_command('LIndex', $key, $index);
	}

	public function LInsert($key, $after = true, $pivot, $value)
	{
		if ($after) $position = self::Position_AFTER;
		else $position = self::Position_BEFORE;
		return $this->send_command('LInsert', $key, $position, $pivot, $value);
	}

	public function LLen($key)
	{
		return $this->send_command('LLen', $key);
	}

	public function LPop($key)
	{
		return $this->send_command('LPop', $key);
	}

	public function LPush($key, $value)
	{
		if (!is_array($value)) $value = func_get_args();
		else array_unshift($value, $key);
		return $this->__call('lpush', $value);
	}

	public function LPushX($key, $value)
	{
		return $this->send_command('LPushX', $key, $value);
	}

	public function LRange($key, $start, $stop)
	{
		return $this->send_command('LRange', $key, $start, $stop);
	}

	public function LRem($key, $count, $value)
	{
		return $this->send_command('LRem', $key, $count, $value);
	}

	public function LSet($key, $index, $value)
	{
		return $this->send_command('LSet', $key, $index, $value);
	}

	public function LTrim($key, $start, $stop)
	{
		return $this->send_command('LTrim', $key, $start, $stop);
	}

	public function MGet($key)
	{
		if (!is_array($key)) $key = func_get_args();
		return $this->__call('MGet', $key);
	}

	public function Move($key, $db)
	{
		return $this->send_command('Move', $key, $db);
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
		return $this->send_command('Persist', $key);
	}

	public function PSubscribe($pattern)
	{
		return $this->send_command('PSubscribe', $pattern);
	}

	public function Publish($channel, $message)
	{
		return $this->send_command('Publish', $channel, $message);
	}

	public function PUnsubscribe($patterns = null)
	{
		if (!empty($patterns))
		{
			if (!is_array($patterns)) $patterns = array($patterns);
			return $this->__call('PUnsubscribe', $patterns);
		}
		else return $this->send_command('PUnsubscribe');
	}

	public function Quit()
	{
		return $this->send_command('Quit');
	}

	public function Rename($key, $newkey)
	{
		return $this->send_command('Rename', $key, $newkey);
	}

	public function RenameNX($key, $newkey)
	{
		return $this->send_command('RenameNX', $key, $newkey);
	}

	public function RPop($key)
	{
		return $this->send_command('RPop', $key);
	}

	public function RPopLPush($source, $destination)
	{
		return $this->send_command('RPopLPush', $source, $destination);
	}

	public function RPush($key, $value)
	{
		if (!is_array($value)) $value = func_get_args();
		else array_unshift($value, $key);
		return $this->__call('rpush', $value);
	}

	public function RPushX($key, $value)
	{
		return $this->send_command('RPushX', $key, $value);
	}

	public function sCard($key)
	{
		return $this->send_command('sCard', $key);
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
		return $this->send_command('Select', $index);
	}

	public function SetBit($key, $offset, $value)
	{
		return $this->send_command('SetBit', $key, $offset, $value);
	}

	public function SetRange($key, $offset, $value)
	{
		return $this->send_command('SetRange', $key, $offset, $value);
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
		return $this->send_command('SlaveOf', $host, $port);
	}

	public function sMove($source, $destination, $member)
	{
		return $this->send_command('sMove', $source, $destination, $member);
	}

	public function Sort($key, $sort_rule)
	{
		return $this->send_command('Sort', $key, $sort_rule);
	}

	public function StrLen($key)
	{
		return $this->send_command('StrLen', $key);
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
		return $this->send_command('Type', $key);
	}

	public function Unsubscribe($channel = '')
	{
		$args = func_get_args();
		if (empty($args)) return $this->send_command('Unsubscribe');
		else
		{
			if (is_array($channel)) return $this->__call('Unsubscribe', $channel);
			else return $this->__call('Unsubscribe', $args);
		}
	}

	public function Unwatch()
	{
		return $this->send_command('Unwatch');
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
		return $this->send_command('zCard', $key);
	}

	public function zCount($key, $min, $max)
	{
		return $this->send_command('zCount', $key, $min, $max);
	}

	public function zIncrBy($key, $increment, $member)
	{
		return $this->send_command('zIncrBy', $key, $increment, $member);
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
		if ($withscores) return $this->send_command('zRange', $key, $start, $stop, self::WITHSCORES);
		else return $this->send_command('zRange', $key, $start, $stop);
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
		return $this->send_command('zRank', $key, $member);
	}

	public function zRem($key, $member)
	{
		if (!is_array($member)) $member = func_get_args();
		else array_unshift($member, $key);
		return $this->__call('zrem', $member);
	}

	public function zRemRangeByRank($key, $start, $stop)
	{
		return $this->send_command('zRemRangeByRank', $key, $start, $stop);
	}

	public function zRemRangeByScore($key, $min, $max)
	{
		return $this->send_command('zRemRangeByScore', $key, $min, $max);
	}

	public function zRevRange($key, $start, $stop, $withscores = false)
	{
		if ($withscores) return $this->send_command('zRevRange', $key, $start, $stop, self::WITHSCORES);
		else return $this->send_command('zRevRange', $key, $start, $stop);
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
		return $this->send_command('zRevRank', $key, $member);
	}

	public function zScore($key, $member)
	{
		return $this->send_command('zScore', $key, $member);
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
}
