<?php
namespace Jamm\Memory;

interface IRedisServer
{
	public function Get($key);

	public function Set($key, $value);

	/**
	 * Set the value of a key, only if the key does not exist
	 * @param string $key
	 * @param string $value
	 * @return void
	 */
	public function SetNX($key, $value);

	/**
	 * Set the value and expiration of a key
	 * @param string $key
	 * @param int $seconds
	 * @param string $value
	 * @return boolean
	 */
	public function SetEX($key, $seconds, $value);

	/**
	 * Set a key's time to live in seconds
	 * @param string $key
	 * @param int $seconds
	 * @return boolean
	 */
	public function Expire($key, $seconds);

	/**
	 * Get the time to live for a key
	 * @param string $key
	 * @return int
	 */
	public function TTL($key);

	/**
	 * Delete a key
	 * Takes an array of keys, or an undefined number of parameters, each a key: key1 key2 key3 ... keyN
	 * @return int
	 */
	public function Del();

	/**
	 * Increment the integer value of a key by the given number
	 * @param string $key
	 * @param int $increment
	 * @return int
	 */
	public function IncrBy($key, $increment);

	/**
	 * Append a value to a key
	 * @param string $key
	 * @param string $value
	 * @return int
	 */
	public function Append($key, $value);

	/**
	 * Returns all keys matching pattern.
	 * @param string $pattern
	 *  Supported glob-style patterns:
	 *   h?llo matches hello, hallo and hxllo
	 *   h*llo matches hllo and heeeello
	 *   h[ae]llo matches hello and hallo, but not hillo
	 *  Use \ to escape special characters if you want to match them verbatim.
	 * @return array
	 */
	public function Keys($pattern);

	/** Mark the start of a transaction block */
	public function multi();

	/**
	 * Marks the given keys to be watched for conditional execution of a transaction
	 * each argument is a key:
	 * watch('key1', 'key2', 'key3', ...)
	 */
	public function watch();

	/**
	 * Executes all previously queued commands in a transaction and restores the connection state to normal.
	 * When using WATCH, EXEC will execute commands only if the watched keys were not modified, allowing for a check-and-set mechanism.
	 */
	public function exec();

	/**
	 * Flushes all previously queued commands in a transaction and restores the connection state to normal.
	 * If WATCH was used, DISCARD unwatches all keys.
	 */
	public function discard();

	/** Add a member to a set
	 * @param string $set
	 * @param string $value
	 * @return boolean
	 */
	public function sAdd($set, $value);

	/**
	 * Returns if value is a member of the set.
	 * @param string $set
	 * @param string $value
	 * @return boolean
	 */
	public function sIsMember($set, $value);

	/**
	 * Returns all the members of the set.
	 * @param string $set
	 * @return array
	 */
	public function sMembers($set);

	/**
	 * Remove member from the set. If 'value' is not a member of this set, no operation is performed.
	 * An error is returned when the value stored at key is not a set.
	 * @param string $set
	 * @param string $value
	 * @return boolean
	 */
	public function sRem($set, $value);

	/** Get information and statistics about the server */
	public function info();
}
