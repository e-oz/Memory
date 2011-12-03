<?php
namespace Jamm\Memory;

/**
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
interface IRedisServer
{
	const Position_BEFORE = 'BEFORE';
	const Position_AFTER  = 'AFTER';
	const WITHSCORES      = 'WITHSCORES';
	const Aggregate_SUM   = 'SUM';
	const Aggregate_MIN   = 'MIN';
	const Aggregate_MAX   = 'MAX';

	/**
	 * Append a value to a key
	 * @param string $key
	 * @param string $value
	 * @return int
	 */
	public function Append($key, $value);

	/**
	 * Request for authentication in a password protected Redis server.
	 * @param string $pasword
	 * @return boolean
	 */
	public function Auth($pasword);

	/** Rewrites the append-only file to reflect the current dataset in memory. */
	public function bgRewriteAOF();

	/** Asynchronously save the dataset to disk */
	public function bgSave();

	/**
	 * Remove and get the first element in a list, or block until one is available
	 * Parameters format:
	 *  key1,key2,key3,...,keyN,timeout
	 * or
	 *  array(key1,key2,keyN), timeout
	 * @param string|array $keys
	 * @param int $timeout - time of waiting
	 */
	public function BLPop($keys, $timeout);

	/**
	 * Remove and get the last element in a list, or block until one is available
	 * Parameters format:
	 *  key1,key2,key3,...,keyN,timeout
	 * or
	 *  array(key1,key2,keyN), timeout
	 * @param string|array $keys
	 * @param int $timeout - time of waiting
	 */
	public function BRPop($keys, $timeout);

	/**
	 * Pop a value from a list, push it to another list and return it; or block until one is available
	 * @param $source
	 * @param $destination
	 * @param $timeout
	 * @return string|boolean
	 */
	public function BRPopLPush($source, $destination, $timeout);

	/**
	 * Get the value of a configuration parameter
	 * @param string $pattern
	 * @return string
	 */
	public function Config_Get($pattern);

	/**
	 * Set the value of a configuration parameter
	 * @param $parameter
	 * @param $value
	 * @return boolean
	 */
	public function Config_Set($parameter, $value);

	/**
	 * Resets the statistics reported by Redis using the INFO command.
	 * These are the counters that are reset:
	 * Keyspace hits
	 * Keyspace misses
	 * Number of commands processed
	 * Number of connections received
	 * Number of expired keys
	 */
	public function Config_ResetStat();

	/**
	 * Return the number of keys in the selected database
	 * @return int
	 */
	public function DBsize();

	/**
	 * Decrement the integer value of a key by one
	 * @param string $key
	 * @return int
	 */
	public function Decr($key);

	/**
	 * Decrement the integer value of a key by the given number
	 * @param string $key
	 * @param int $decrement
	 * @return int
	 */
	public function DecrBy($key, $decrement);

	/**
	 * Delete a key
	 * Parameters: $key1, $key2, ...
	 * or: array($key1, $key2, ...)
	 * @param string $key
	 * @return int
	 */
	public function Del($key);

	/**
	 * Determine if a key exists
	 * @param string $key
	 * @return int
	 */
	public function Exists($key);

	/**
	 * Set the expiration for a key as a UNIX timestamp
	 * @param string $key
	 * @param int $timestamp
	 * @return int
	 */
	public function Expireat($key, $timestamp);

	/** Remove all keys from all databases */
	public function FlushAll();

	/** Remove all keys from the current database */
	public function FlushDB();

	/**
	 * Get the value of a key
	 * @param string $key
	 * @return string
	 */
	public function Get($key);

	/**
	 * Returns the bit value at offset in the string value stored at key
	 * @param string $key
	 * @param int $offset
	 */
	public function GetBit($key, $offset);

	/**
	 * Get a substring of the string stored at a key
	 * @param string $key
	 * @param int $start
	 * @param int $end
	 * @return string
	 */
	public function GetRange($key, $start, $end);

	/**
	 * Atomically sets key to value and returns the old value stored at key.
	 * Returns an error when key exists but does not hold a string value.
	 * Usage:
	 * From time to time we need to get the value of the counter and reset it to zero atomically.
	 * This can be done using GETSET mycounter "0".
	 * @param string $key
	 * @param string $value
	 * @return string
	 */
	public function GetSet($key, $value);

	/**
	 * Removes the specified fields from the hash stored at key.
	 * Non-existing fields are ignored. Non-existing keys are treated as empty hashes and this command returns 0.
	 * Parameters: ($key, $field1, $field2...)
	 * or: ($key, array($field1,$field2...))
	 * @param string $key
	 * @param array|string $field
	 * @return int
	 */
	public function hDel($key, $field);

	/**
	 * Determine if a hash field exists
	 * @param string $key
	 * @param string $field
	 * @return int
	 */
	public function hExists($key, $field);

	/**
	 * Get the value of a hash field
	 * @param string $key
	 * @param string $field
	 * @return string|int
	 */
	public function hGet($key, $field);

	/**
	 * Get all the fields and values in a hash
	 * @param string $key
	 * @return array
	 */
	public function hGetAll($key);

	/**
	 * Increments the number stored at field in the hash stored at key by increment.
	 * If key does not exist, a new key holding a hash is created.
	 * If field does not exist or holds a string that cannot be interpreted as integer, the value is set to 0 before the operation is performed.
	 * Returns the value at field after the increment operation.
	 * @param string $key
	 * @param string $field
	 * @param int $increment
	 * @return int
	 */
	public function hIncrBy($key, $field, $increment);

	/**
	 * Get all the fields in a hash
	 * @param string $key name of hash
	 * @return array
	 */
	public function hKeys($key);

	/**
	 * Get the number of fields in a hash
	 * @param string $key
	 * @return int
	 */
	public function hLen($key);

	/**
	 * Returns the values associated with the specified fields in the hash stored at key.
	 * For every field that does not exist in the hash, a nil value is returned.
	 * @param string $key
	 * @param array $fields
	 * @return array
	 */
	public function hMGet($key, array $fields);

	/**
	 * Set multiple hash fields to multiple values
	 * @param string $key
	 * @param array $fields (field => value)
	 */
	public function hMSet($key, $fields);

	/**
	 * Set the string value of a hash field
	 * @param string $key hash
	 * @param string $field
	 * @param string $value
	 * @return int
	 */
	public function hSet($key, $field, $value);

	/**
	 * Set the value of a hash field, only if the field does not exist
	 * @param string $key
	 * @param string $field
	 * @param string $value
	 * @return int
	 */
	public function hSetNX($key, $field, $value);

	/**
	 * Get all the values in a hash
	 * @param string $key
	 * @return array
	 */
	public function hVals($key);

	/**
	 * Increment the integer value of a key by one
	 * @param string $key
	 * @return int
	 */
	public function Incr($key);

	/**
	 * Returns the element at index $index in the list stored at $key.
	 * The index is zero-based, so 0 means the first element, 1 the second element and so on.
	 * Negative indices can be used to designate elements starting at the tail of the list.
	 * Here, -1 means the last element, -2 means the penultimate and so forth.
	 * When the value at key is not a list, an error is returned.
	 * @param string $key
	 * @param int $index
	 * @return string|boolean
	 */
	public function LIndex($key, $index);

	/**
	 * Insert an element before or after another element in a list
	 * @param string $key
	 * @param bool $after
	 * @param string $pivot
	 * @param string $value
	 * @return int
	 */
	public function LInsert($key, $after = true, $pivot, $value);

	/**
	 * Get the length of a list
	 * @param string $key
	 * @return int
	 */
	public function LLen($key);

	/**
	 * Remove and get the first element in a list
	 * @param string $key
	 * @return string|boolean
	 */
	public function LPop($key);

	/**
	 * Inserts value at the head of the list stored at key.
	 * If key does not exist, it is created as empty list before performing the push operation.
	 * When key holds a value that is not a list, an error is returned.
	 * @param string $key
	 * @param string|array $value
	 * @usage
	 * LPush(key, value)
	 * LPush(key, value1, value2)
	 * LPush(key, array(value1, value2))
	 * @return int
	 */
	public function LPush($key, $value);

	/**
	 * Inserts value at the head of the list stored at key, only if key already exists and holds a list.
	 * In contrary to LPush, no operation will be performed when key does not yet exist.
	 * @param string $key
	 * @param string $value
	 * @return int
	 */
	public function LPushX($key, $value);

	/**
	 * Returns the specified elements of the list stored at key.
	 * The offsets $start and $stop are zero-based indexes, with 0 being the first element of the list (the head of the list),
	 * 1 being the next element and so on.
	 * These offsets can also be negative numbers indicating offsets starting at the end of the list.
	 * For example, -1 is the last element of the list, -2 the penultimate, and so on.
	 * @param string $key
	 * @param int $start
	 * @param int $stop
	 * @return array
	 */
	public function LRange($key, $start, $stop);

	/**
	 * Removes the first count occurrences of elements equal to value from the list stored at key.
	 * The count argument influences the operation in the following ways:
	 *  count > 0: Remove elements equal to value moving from head to tail.
	 *  count < 0: Remove elements equal to value moving from tail to head.
	 *  count = 0: Remove all elements equal to value.
	 * For example, LREM list -2 "hello" will remove the last two occurrences of "hello" in the list stored at list.
	 * @param string $key
	 * @param int $count
	 * @param string $value
	 * @return int
	 */
	public function LRem($key, $count, $value);

	/**
	 * Sets the list element at index to value.
	 * For more information on the index argument, see LINDEX.
	 * An error is returned for out of range indexes.
	 * @param $key
	 * @param $index
	 * @param $value
	 * @return boolean
	 */
	public function LSet($key, $index, $value);

	/**
	 * Trim a list to the specified range
	 * @link http://redis.io/commands/ltrim
	 * @param string $key
	 * @param int $start
	 * @param int $stop
	 * @return boolean
	 */
	public function LTrim($key, $start, $stop);

	/**
	 * Returns the values of all specified keys.
	 * For every key that does not hold a string value or does not exist, the special value nil is returned.
	 * Parameters: $key, [key ...]
	 * or: array($key1, $key2...)
	 * @param string $key
	 * @return array
	 */
	public function MGet($key);

	/**
	 * Move key from the currently selected database (see SELECT) to the specified destination database.
	 * When key already exists in the destination database, or it does not exist in the source database, it does nothing.
	 * It is possible to use MOVE as a locking primitive because of this.
	 * @param string $key
	 * @param int $db
	 * @return int
	 */
	public function Move($key, $db);

	/**
	 * Set multiple keys to multiple values
	 * @param array $keys (key => value)
	 * @return string
	 */
	public function MSet(array $keys);

	/**
	 * Set multiple keys to multiple values, only if none of the keys exist
	 * @param array $keys (key => value)
	 * Returns:
	 * 1 if the all the keys were set.
	 * 0 if no key was set (at least one key already existed).
	 * @return int
	 */
	public function MSetNX(array $keys);

	/**
	 * Remove the expiration from a key
	 * @param string $key
	 * @return int
	 */
	public function Persist($key);

	/**
	 * Subscribes the client to the given patterns.
	 * @param string $pattern
	 */
	public function PSubscribe($pattern);

	/**
	 * Post a message to a channel
	 * Returns the number of clients that received the message.
	 * @param string $channel
	 * @param string $message
	 * @return int
	 */
	public function Publish($channel, $message);

	/**
	 * Stop listening for messages posted to channels matching the given patterns
	 * @param array|string|null $patterns
	 * @return int
	 */
	public function PUnsubscribe($patterns = null);

	/** Close the connection */
	public function Quit();

	/**
	 * Renames key to newkey.
	 * It returns an error when the source and destination names are the same, or when key does not exist.
	 * If newkey already exists it is overwritten.
	 * @param string $key
	 * @param string $newkey
	 * @return boolean
	 */
	public function Rename($key, $newkey);

	/**
	 * Rename a key, only if the new key does not exist
	 * @param string $key
	 * @param string $newkey
	 * @return int
	 */
	public function RenameNX($key, $newkey);

	/**
	 * Removes and returns the last element of the list stored at key.
	 * @param string $key
	 * @return string|boolean
	 */
	public function RPop($key);

	/**
	 * Atomically returns and removes the last element (tail) of the list stored at source,
	 * and pushes the element at the first element (head) of the list stored at destination.
	 * If source does not exist, the value nil is returned and no operation is performed.
	 * @param string $source
	 * @param string $destination
	 * @return string
	 */
	public function RPopLPush($source, $destination);

	/**
	 * Inserts value at the tail of the list stored at key.
	 * If key does not exist, it is created as empty list before performing the push operation.
	 * When key holds a value that is not a list, an error is returned.
	 * Parameters: key value [value ...]
	 * or: key, array(value,value,...)
	 * @param string $key
	 * @param string|array $value
	 * @return int|boolean
	 */
	public function RPush($key, $value);

	/**
	 * Append a value to a list, only if the list exists
	 * @param string $key
	 * @param string $value
	 * @return int
	 */
	public function RPushX($key, $value);

	/**
	 * Get the number of members in a set
	 * @param string $key
	 * @return int
	 */
	public function sCard($key);

	/**
	 * Returns the members of the set resulting from the difference between the first set and all the successive sets.
	 * For example:
	 *  key1 = {a,b,c,d}
	 *  key2 = {c}
	 *  key3 = {a,c,e}
	 *  SDIFF key1 key2 key3 = {b,d}
	 * Keys that do not exist are considered to be empty sets.
	 *
	 * Parameters: key1, key2, key3...
	 * @param string|array $key
	 * @return array
	 */
	public function sDiff($key);

	/**
	 * This command is equal to SDIFF, but instead of returning the resulting set, it is stored in destination.
	 * If destination already exists, it is overwritten.
	 * Returns the number of elements in the resulting set.
	 * Parameters: destination, key [key, ...]
	 * or: destination, array(key,key, ...)
	 * @param string $destination
	 * @param string|array $key
	 * @return int
	 */
	public function sDiffStore($destination, $key);

	/**
	 * Select the DB with having the specified zero-based numeric index. New connections always use DB 0.
	 * @param int $index
	 */
	public function Select($index);

	/**
	 * Set key to hold the string value. If key already holds a value, it is overwritten, regardless of its type.
	 * @param string $key
	 * @param string $value
	 * @return string
	 */
	public function Set($key, $value);

	/**
	 * Sets or clears the bit at offset in the string value stored at key
	 * @link http://redis.io/commands/setbit
	 * @param string $key
	 * @param int $offset
	 * @param int $value
	 * Returns the original bit value stored at offset.
	 * @return int
	 */
	public function SetBit($key, $offset, $value);

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
	 * Overwrites part of the string stored at key, starting at the specified offset, for the entire length of value.
	 * If the offset is larger than the current length of the string at key, the string is padded with zero-bytes to make offset fit.
	 * Non-existing keys are considered as empty strings, so this command will make sure it holds a string large enough
	 * to be able to set value at offset.
	 *
	 * Thanks to SETRANGE and the analogous GETRANGE commands, you can use Redis strings as a linear array with O(1) random access.
	 * This is a very fast and efficient storage in many real world use cases.
	 * @link http://redis.io/commands/setrange
	 * @param string $key
	 * @param int $offset
	 * @param string $value
	 * Returns the length of the string after it was modified by the command.
	 * @return int
	 */
	public function SetRange($key, $offset, $value);

	/**
	 * Returns the members of the set resulting from the intersection of all the given sets.
	 * For example:
	 *  key1 = {a,b,c,d}
	 *  key2 = {c}
	 *  key3 = {a,c,e}
	 *  SINTER key1 key2 key3 = {c}
	 * Parameters: key [key ...]
	 * or: array(key, key, ...)
	 * @param string|array $key
	 * @return array
	 */
	public function sInter($key);

	/**
	 * Intersect multiple sets and store the resulting set in a key
	 * This command is equal to SINTER, but instead of returning the resulting set, it is stored in destination.
	 * If destination already exists, it is overwritten.
	 * Parameters: $destination,$key [key ...]
	 * or: $destination, array($key, $key...)
	 * @param string $destination
	 * @param string|array $key
	 * Returns the number of elements in the resulting set.
	 * @return int
	 */
	public function sInterStore($destination, $key);

	/**
	 * Make the server a slave of another instance, or promote it as master
	 * @link http://redis.io/commands/slaveof
	 * @param string $host
	 * @param int $port
	 * @return string
	 */
	public function SlaveOf($host, $port);

	/**
	 * Move member from the set at source to the set at destination.
	 * This operation is atomic.
	 * In every given moment the element will appear to be a member of source or destination for other clients.
	 * If the source set does not exist or does not contain the specified element, no operation is performed and 0 is returned.
	 * Otherwise, the element is removed from the source set and added to the destination set.
	 * When the specified element already exists in the destination set, it is only removed from the source set.
	 * @param string $source
	 * @param string $destination
	 * @param string $member
	 * @return int
	 */
	public function sMove($source, $destination, $member);

	/**
	 * Sort the elements in a list, set or sorted set
	 * @link http://redis.io/commands/sort
	 * @param string $key
	 * @param string $sort_rule [BY pattern] [LIMIT offset count] [GET pattern [GET pattern ...]] [ASC|DESC] [ALPHA] [STORE destination]
	 * Returns list of sorted elements.
	 * @return array
	 */
	public function Sort($key, $sort_rule);

	/**
	 * Get the length of the value stored in a key
	 * @param string $key
	 * @return int
	 */
	public function StrLen($key);

	/** Subscribes the client to the specified channels.
	 * Once the client enters the subscribed state it is not supposed to issue any other commands,
	 * except for additional SUBSCRIBE, PSUBSCRIBE, UNSUBSCRIBE and PUNSUBSCRIBE commands.
	 * @param $channel
	 * Parameters: $channel [channel ...]
	 */
	public function Subscribe($channel);

	/**
	 * Returns the members of the set resulting from the union of all the given sets.
	 * For example:
	 *  key1 = {a,b,c,d}
	 *  key2 = {c}
	 *  key3 = {a,c,e}
	 * SUNION key1 key2 key3 = {a,b,c,d,e}
	 * Parameters: key [key...]
	 * @param string|array $key
	 * @return array
	 */
	public function sUnion($key);

	/**
	 * Add multiple sets and store the resulting set in a key
	 * Parameters: $destination, $key [key ...]
	 * @param $destination
	 * @param string|array $key
	 * Returns the number of elements in the resulting set.
	 * @return int
	 */
	public function sUnionStore($destination, $key);

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
	 * Returns the string representation of the type of the value stored at key.
	 * The different types that can be returned are: string, list, set, zset and hash.
	 * @param string $key
	 * Returns type of key, or 'none' when key does not exist.
	 * @return string
	 */
	public function Type($key);

	/**
	 * Unsubscribes the client from the given channels, or from all of them if none is given.
	 * Parameters: [channel [channel ...]]
	 * @param string $channel
	 */
	public function Unsubscribe($channel = '');

	/** Forget about all watched keys */
	public function Unwatch();

	/**
	 * Add a member to a sorted set, or update its score if it already exists
	 * @param string $key
	 * @param int|array $score
	 * @param string $member
	 * Can be used as:
	 * zadd(key, score, member)
	 * zadd(key, score1, member1, score2, member2)
	 * zadd(key, array(score1 => member1, score2 => member2))
	 * @return int
	 */
	public function zAdd($key, $score, $member = NULL);

	/**
	 * Get the number of members in a sorted set
	 * @param string $key
	 * @return int
	 */
	public function zCard($key);

	/**
	 * Returns the number of elements in the sorted set at key with a score between min and max.
	 * The min and max arguments have the same semantic as described for ZRANGEBYSCORE.
	 * @param string $key
	 * @param string|int $min
	 * @param string|int $max
	 * @return int
	 */
	public function zCount($key, $min, $max);

	/**
	 * Increment the score of a member in a sorted set
	 * @param string $key
	 * @param number $increment
	 * @param string $member
	 * @return number
	 */
	public function zIncrBy($key, $increment, $member);

	/**
	 * Intersect multiple sorted sets and store the resulting sorted set in a new key
	 * @link http://redis.io/commands/zinterstore
	 * @param string $destination
	 * @param array $keys
	 * @param array|null $weights
	 * @param string|null $aggregate see Aggregate* constants
	 * Returns the number of elements in the resulting sorted set at destination.
	 * @return int
	 */
	public function zInterStore($destination, array $keys, array $weights = null, $aggregate = null);

	/**
	 * @abstract
	 * @param string $key
	 * @param int $start
	 * @param int $stop
	 * @param bool $withscores
	 * @return array
	 */
	public function zRange($key, $start, $stop, $withscores = false);

	/**
	 * Return a range of members in a sorted set, by score
	 * @link http://redis.io/commands/zrangebyscore
	 * @param string $key
	 * @param string|number $min
	 * @param string|number $max
	 * @param bool $withscores
	 * @param array|null $limit
	 * @return array
	 */
	public function zRangeByScore($key, $min, $max, $withscores = false, array $limit = null);

	/**
	 * Returns the rank of member in the sorted set stored at key, with the scores ordered from low to high.
	 * The rank (or index) is 0-based, which means that the member with the lowest score has rank 0.
	 * Use ZREVRANK to get the rank of an element with the scores ordered from high to low.
	 * @param string $key
	 * @param string $member
	 * @return int|boolean
	 */
	public function zRank($key, $member);

	/**
	 * Remove a member from a sorted set
	 * @param string $key
	 * @param string|array $member
	 * @usage
	 * zrem(key, member)
	 * zrem(key, member1, member2)
	 * zrem(key, array(member1, member2))
	 * @return int
	 */
	public function zRem($key, $member);

	/**
	 * Removes all elements in the sorted set stored at key with rank between start and stop.
	 * Both start and stop are 0-based indexes with 0 being the element with the lowest score.
	 * These indexes can be negative numbers, where they indicate offsets starting at the element with the highest score.
	 * For example: -1 is the element with the highest score, -2 the element with the second highest score and so forth.
	 * @param string $key
	 * @param int $start
	 * @param int $stop
	 * Returns the number of elements removed.
	 * @return int
	 */
	public function zRemRangeByRank($key, $start, $stop);

	/**
	 * Remove all members in a sorted set within the given scores
	 * @param string $key
	 * @param string|number $min
	 * @param string|number $max
	 * @return int
	 */
	public function zRemRangeByScore($key, $min, $max);

	/**
	 * Returns the specified range of elements in the sorted set stored at key.
	 * The elements are considered to be ordered from the highest to the lowest score.
	 * Descending lexicographical order is used for elements with equal score.
	 * @param string $key
	 * @param int $start
	 * @param int $stop
	 * @param bool $withscores
	 * @return array
	 */
	public function zRevRange($key, $start, $stop, $withscores = false);

	/**
	 * Returns all the elements in the sorted set at key with a score between max and min
	 * (including elements with score equal to max or min).
	 * In contrary to the default ordering of sorted sets, for this command
	 * the elements are considered to be ordered from high to low scores.
	 * The elements having the same score are returned in reverse lexicographical order.
	 * @param string $key
	 * @param number $max
	 * @param number $min
	 * @param bool $withscores
	 * @param array|null $limit (offset, count)
	 * @return array
	 */
	public function zRevRangeByScore($key, $max, $min, $withscores = false, array $limit = null);

	/**
	 * Returns the rank of member in the sorted set stored at key, with the scores ordered from high to low.
	 * The rank (or index) is 0-based, which means that the member with the highest score has rank 0.
	 * Use ZRANK to get the rank of an element with the scores ordered from low to high.
	 * @param string $key
	 * @param string $member
	 * @return int|boolean
	 */
	public function zRevRank($key, $member);

	/**
	 * Get the score associated with the given member in a sorted set
	 * @param string $key
	 * @param string $member
	 * @return string
	 */
	public function zScore($key, $member);

	/**
	 * Add multiple sorted sets and store the resulting sorted set in a new key
	 * @link http://redis.io/commands/zunionstore
	 * @param string $destination
	 * @param array $keys
	 * @param array|null $weights
	 * @param string|null $aggregate see Aggregate* constants
	 * @return int
	 */
	public function zUnionStore($destination, array $keys, array $weights = null, $aggregate = null);

	/**
	 * Increment the integer value of a key by the given number
	 * @param string $key
	 * @param int $increment
	 * @return int
	 */
	public function IncrBy($key, $increment);

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
	public function Multi();

	/**
	 * Marks the given keys to be watched for conditional execution of a transaction
	 * each argument is a key:
	 * watch('key1', 'key2', 'key3', ...)
	 */
	public function Watch();

	/**
	 * Executes all previously queued commands in a transaction and restores the connection state to normal.
	 * When using WATCH, EXEC will execute commands only if the watched keys were not modified, allowing for a check-and-set mechanism.
	 */
	public function Exec();

	/**
	 * Flushes all previously queued commands in a transaction and restores the connection state to normal.
	 * If WATCH was used, DISCARD unwatches all keys.
	 */
	public function Discard();

	/** Add a member to a set
	 * @param string $set
	 * @param string|array $value or multiple arguments
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
	public function Info();
}
