<?php
namespace Jamm\Memory;

class RedisServer extends MemoryObject implements IRedisServer
{
	protected $connection;

	public function __construct($host = 'localhost', $port = '6379')
	{
		$this->connection = $this->connect($host, $port);
	}

	protected function connect($host, $port)
	{
		$socket = fsockopen($host, $port, $errno, $errstr);
		if (!$socket) return $this->ReportError('Connection error: '.$errno.':'.$errstr, __LINE__);
		return $socket;
	}

	/**
	 * Execute command and return the result
	 * Each entity of the command should be passed as argument
	 * Example:
	 *  command('set','key','example value');
	 * or:
	 *  command('multi');
	 *  command('set','a', serialize($arr));
	 *  command('set','b', 1);
	 *  command('execute');
	 * @return array|bool|int|null|string
	 */
	public function command()
	{
		$args = func_get_args();

		$command = '*'.count($args)."\r\n";
		foreach ($args as $arg) $command .= "$".strlen($arg)."\r\n".$arg."\r\n";

		$w = fwrite($this->connection, $command);
		if (!$w) return $this->ReportError('command was not sent', __LINE__);
		return $this->read_reply();
	}

	public function read_reply()
	{
		$reply = trim(fgets($this->connection));
		$response = null;

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
				$chi = 0;
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

	public function get($key)
	{
		return $this->command('get', $key);
	}

	public function set($key, $value)
	{
		return $this->command('set', $key, $value);
	}

	public function SetEx($key, $seconds, $value)
	{
		return $this->command('setex', $key, $seconds, $value);
	}

	public function Keys($pattern)
	{
		return $this->command('keys', $pattern);
	}

	public function multi()
	{
		$this->command('multi');
		return $this;
	}

	public function sAdd($set, $value)
	{
		return $this->command('sadd', $set, $value);
	}

	public function sMembers($set)
	{
		return $this->command('smembers', $set);
	}

	public function hSet($hash, $field, $value)
	{
		return $this->command('hset', $hash, $field, $value);
	}

	public function hGetAll($hash)
	{
		$arr = $this->command('hgetall', $hash);
		$c = count($arr);
		$r = array();
		for ($i = 0; $i < $c; $i += 2)
		{
			$r[$arr[$i]] = $arr[$i+1];
		}
		return $r;
	}

	public function FlushDB()
	{
		return $this->command('flushdb');
	}

	public function info()
	{
		return $this->command('info');
	}

	public function __destruct()
	{
		if (!empty($this->connection)) fclose($this->connection);
	}

	/**
	 * Set the value of a key, only if the key does not exist
	 * @param string $key
	 * @param string $value
	 */
	public function SetNX($key, $value)
	{
		return $this->command('setnx', $key, $value);
	}

	/**
	 * Marks the given keys to be watched for conditional execution of a transaction
	 * each argument is a key:
	 * watch('key1', 'key2', 'key3', ...)
	 */
	public function watch()
	{
		$args = func_get_args();
		array_unshift($args, 'watch');
		return call_user_func_array(array($this, 'command'), $args);
	}

	/**
	 * Executes all previously queued commands in a transaction and restores the connection state to normal.
	 * When using WATCH, EXEC will execute commands only if the watched keys were not modified, allowing for a check-and-set mechanism.
	 */
	public function exec()
	{
		return $this->command('exec');
	}

	/**
	 * Flushes all previously queued commands in a transaction and restores the connection state to normal.
	 * If WATCH was used, DISCARD unwatches all keys.
	 */
	public function discard()
	{
		return $this->command('discard');
	}

	/**
	 * Returns if value is a member of the set.
	 * @param string $set
	 * @param string $value
	 * @return boolean
	 */
	public function sIsMember($set, $value)
	{
		return $this->command('sismember', $set, $value);
	}

	/**
	 * Remove member from the set. If 'value' is not a member of this set, no operation is performed.
	 * An error is returned when the value stored at key is not a set.
	 * @param string $set
	 * @param string $value
	 * @return boolean
	 */
	public function sRem($set, $value)
	{
		return $this->command('srem', $set, $value);
	}

	public function Expire($key, $seconds)
	{
		return $this->command('expire', $key, $seconds);
	}

	public function TTL($key)
	{
		return $this->command('ttl', $key);
	}

	/**
	 * Delete a key
	 * Takes an array of keys, or an undefined number of parameters, each a key: key1 key2 key3 ... keyN
	 * @return int
	 */
	public function Del()
	{
		$args = func_get_args();
		if (is_array($args[0])) $args = $args[0];
		array_unshift($args, 'del');
		return call_user_func_array(array($this, 'command'), $args);
	}

	/**
	 * Increment the integer value of a key by the given number
	 * @param string $key
	 * @param int $increment
	 * @return int
	 */
	public function IncrBy($key, $increment)
	{
		return $this->command('incrby', $key, $increment);
	}

	/**
	 * Append a value to a key
	 * @param string $key
	 * @param string $value
	 * @return int
	 */
	public function Append($key, $value)
	{
		return $this->command('append', $key, $value);
	}
}
