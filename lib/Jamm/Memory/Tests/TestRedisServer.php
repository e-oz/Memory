<?php
namespace Jamm\Memory\Tests;
class TestRedisServer extends \Jamm\Tester\ClassTest
{
	/** @var \Jamm\Memory\Tests\MockRedisServer */
	protected $redis;
	protected $results = array();

	public function __construct()
	{
		$this->redis = new MockRedisServer();
	}

	public function test_Append()
	{
		$this->assertEquals('append key value', $this->redis->Append('key', 'value'));
	}

	public function test_Auth()
	{
		$this->assertEquals('auth pass', $this->redis->Auth('pass'));
	}

	public function test_bgRewriteAOF()
	{
		$this->assertEquals('bgrewriteaof', $this->redis->bgRewriteAOF());
	}

	public function test_bgSave()
	{
		$this->assertEquals('bgsave', $this->redis->bgSave());
	}

	public function test_BLPop()
	{
		$this->assertEquals('blpop key1 50', $this->redis->BLPop('key1', 50));
		$this->assertEquals('blpop key1 key2 50', $this->redis->BLPop('key1', 'key2', 50));
		$this->assertEquals('blpop key1 key2 key3 50', $this->redis->BLPop(array('key1', 'key2', 'key3'), 50));
	}

	public function test_BRPop()
	{
		$this->assertEquals('brpop key1 50', $this->redis->brpop('key1', 50));
		$this->assertEquals('brpop key1 key2 50', $this->redis->brpop('key1', 'key2', 50));
		$this->assertEquals('brpop key1 key2 key3 50', $this->redis->brpop(array('key1', 'key2', 'key3'), 50));
	}

	public function test_BRPopLPush()
	{
		$this->assertEquals('brpoplpush source destination 50', $this->redis->BRPopLPush('source', 'destination', 50));
	}

	public function test_Config_Get()
	{
		$this->assertEquals('config get pattern*', $this->redis->Config_Get('pattern*'));
	}

	public function test_Config_Set()
	{
		$this->assertEquals('config set param val', $this->redis->Config_Set('param', 'val'));
	}

	public function test_Config_ResetStat()
	{
		$this->assertEquals('config resetstat', $this->redis->Config_ResetStat());
	}

	public function test_DBsize()
	{
		$this->assertEquals('dbsize', $this->redis->DBsize());
	}

	public function test_Decr()
	{
		$this->assertEquals('decr key', $this->redis->Decr('key'));
	}

	public function test_DecrBy()
	{
		$this->assertEquals('decrby key 5', $this->redis->DecrBy('key', 5));
	}

	public function test_Del()
	{
		$this->assertEquals('del key', $this->redis->del('key'));
	}

	public function test_Exists()
	{
		$this->assertEquals('exists key', $this->redis->Exists('key'));
	}

	public function test_Expireat()
	{
		$this->assertEquals('expireat key 50', $this->redis->Expireat('key', 50));
	}

	public function test_FlushAll()
	{
		$this->assertEquals('flushall', $this->redis->FlushAll());
	}

	public function test_FlushDB()
	{
		$this->assertEquals('flushdb', $this->redis->FlushDB());
	}

	public function test_Get()
	{
		$this->assertEquals('get key', $this->redis->get('key'));
	}

	public function test_GetBit()
	{
		$this->assertEquals('getbit key 5', $this->redis->GetBit('key', 5));
	}

	public function test_GetRange()
	{
		$this->assertEquals('getrange key 1 2', $this->redis->GetRange('key', 1, 2));
	}

	public function test_GetSet()
	{
		$this->assertEquals('getset k v', $this->redis->GetSet('k', 'v'));
	}

	public function test_hDel()
	{
		$this->assertEquals('hdel key field', $this->redis->hDel('key', 'field'));
		$this->assertEquals('hdel key field field1', $this->redis->hDel('key', 'field', 'field1'));
		$this->assertEquals('hdel key field field1', $this->redis->hDel('key', array('field', 'field1')));
	}

	public function test_hExists()
	{
		$this->assertEquals('hexists key field', $this->redis->hExists('key', 'field'));
	}

	public function test_hGet()
	{
		$this->assertEquals('hget key field', $this->redis->hget('key', 'field'));
	}

	public function test_hGetAll()
	{
		$this->assertEquals(array('h' => 'g'), $this->redis->hGetAll('key'));
	}

	public function test_hIncrBy()
	{
		$this->assertEquals('hincrby key field 50', $this->redis->hIncrBy('key', 'field', 50));
	}

	public function test_hKeys()
	{
		$this->assertEquals('hkeys key', $this->redis->hKeys('key'));
	}

	public function test_hLen()
	{
		$this->assertEquals('hlen k', $this->redis->hLen('k'));
	}

	public function test_hMGet()
	{
		$this->assertEquals('hmget key field1 field2', $this->redis->hMGet('key', array('field1', 'field2')));
	}

	public function test_hMSet()
	{
		$this->assertEquals('hmset key f1 v1 f2 v2', $this->redis->hMSet('key', array('f1' => 'v1', 'f2' => 'v2')));
	}

	public function test_hSet()
	{
		$this->assertEquals('hset key field value', $this->redis->hSet('key', 'field', 'value'));
	}

	public function test_hSetNX()
	{
		$this->assertEquals('hsetnx key field value', $this->redis->hSetNX('key', 'field', 'value'));
	}

	public function test_hVals()
	{
		$this->assertEquals('hvals key', $this->redis->hVals('key'));
	}

	public function test_Incr()
	{
		$this->assertEquals('incr key', $this->redis->Incr('key'));
	}

	public function test_LIndex()
	{
		$this->assertEquals('lindex key index', $this->redis->LIndex('key', 'index'));
	}

	public function test_LInsert()
	{
		$this->assertEquals('linsert key after pivot value', $this->redis->LInsert('key', true, 'pivot', 'value'));
		$this->assertEquals('linsert key before pivot value', $this->redis->LInsert('key', false, 'pivot', 'value'));
	}

	public function test_LLen()
	{
		$this->assertEquals('llen key', $this->redis->LLen('key'));
	}

	public function test_LPop()
	{
		$this->assertEquals('lpop key', $this->redis->LPop('key'));
	}

	public function test_LPush()
	{
		$this->assertEquals('lpush key value', $this->redis->LPush('key', 'value'));
		$this->assertEquals('lpush key value v1 v2', $this->redis->LPush('key', 'value', 'v1', 'v2'));
		$this->assertEquals('lpush key value v1 v2', $this->redis->LPush('key', array('value', 'v1', 'v2')));
	}

	public function test_LPushX()
	{
		$this->assertEquals('lpushx key value', $this->redis->LPushX('key', 'value'));
	}

	public function test_LRange()
	{
		$this->assertEquals('lrange k 3 5', $this->redis->LRange('k', 3, 5));
	}

	public function test_LRem()
	{
		$this->assertEquals('lrem key 5 value', $this->redis->LRem('key', 5, 'value'));
	}

	public function test_LSet()
	{
		$this->assertEquals('lset key index value', $this->redis->LSet('key', 'index', 'value'));
	}

	public function test_LTrim()
	{
		$this->assertEquals('ltrim key 5 7', $this->redis->LTrim('key', 5, 7));
	}

	public function test_MGet()
	{
		$this->assertEquals('mget k', $this->redis->MGet('k'));
		$this->assertEquals('mget k1 k2', $this->redis->MGet(array('k1', 'k2')));
	}

	public function test_Move()
	{
		$this->assertEquals('move key db', $this->redis->Move('key', 'db'));
	}

	public function test_MSet()
	{
		$this->assertEquals('mset k v a b', $this->redis->MSet(array('k' => 'v', 'a' => 'b')));
	}

	public function test_MSetNX()
	{
		$this->assertEquals('msetnx k v a b', $this->redis->MSetNX(array('k' => 'v', 'a' => 'b')));
	}

	public function test_Persist()
	{
		$this->assertEquals('persist key', $this->redis->Persist('key'));
	}

	public function test_PSubscribe()
	{
		$this->assertEquals('psubscribe p*', $this->redis->PSubscribe('p*'));
	}

	public function test_Publish()
	{
		$this->assertEquals('publish c m', $this->redis->Publish('c', 'm'));
	}

	public function test_PUnsubscribe()
	{
		$this->assertEquals('punsubscribe', $this->redis->PUnsubscribe());
		$this->assertEquals('punsubscribe p', $this->redis->PUnsubscribe(array('p')));
		$this->assertEquals('punsubscribe p1 p2', $this->redis->PUnsubscribe(array('p1', 'p2')));
	}

	public function test_Quit()
	{
		$this->assertEquals('quit', $this->redis->Quit());
	}

	public function test_Rename()
	{
		$this->assertEquals('rename key new', $this->redis->Rename('key', 'new'));
	}

	public function test_RenameNX()
	{
		$this->assertEquals('renamenx key new', $this->redis->RenameNX('key', 'new'));
	}

	public function test_RPop()
	{
		$this->assertEquals('rpop k', $this->redis->RPop('k'));
	}

	public function test_RPopLPush()
	{
		$this->assertEquals('rpoplpush s d', $this->redis->RPopLPush('s', 'd'));
	}

	public function test_RPush()
	{
		$this->assertEquals('rpush k v', $this->redis->RPush('k', 'v'));
	}

	public function test_RPushX()
	{
		$this->assertEquals('rpushx k v', $this->redis->RPushx('k', 'v'));
	}

	public function test_sCard()
	{
		$this->assertEquals('scard key', $this->redis->sCard('key'));
	}

	public function test_sDiff()
	{
		$this->assertEquals('sdiff key', $this->redis->sdiff('key'));
	}

	public function test_sDiffStore()
	{
		$this->assertEquals('sdiffstore d k', $this->redis->sDiffStore('d', 'k'));
	}

	public function test_Select()
	{
		$this->assertEquals('select i', $this->redis->Select('i'));
	}

	public function test_Set()
	{
		$this->assertEquals('set k v', $this->redis->set('k', 'v'));
	}

	public function test_SetBit()
	{
		$this->assertEquals('setbit k 5 v', $this->redis->SetBit('k', 5, 'v'));
	}

	public function test_SetNX()
	{
		$this->assertEquals('setnx k v', $this->redis->setnx('k', 'v'));
	}

	public function test_SetEX()
	{
		$this->assertEquals('setex k 5 v', $this->redis->setex('k', 5, 'v'));
	}

	public function test_SetRange()
	{
		$this->assertEquals('setrange k 5 v', $this->redis->setrange('k', 5, 'v'));
	}

	public function test_sInter()
	{
		$this->assertEquals('sinter k', $this->redis->sInter('k'));
		$this->assertEquals('sinter k1 k2', $this->redis->sInter('k1', 'k2'));
		$this->assertEquals('sinter k1 k2', $this->redis->sInter(array('k1', 'k2')));
	}

	public function test_sInterStore()
	{
		$this->assertEquals('sinterstore d k', $this->redis->sInterStore('d', 'k'));
		$this->assertEquals('sinterstore d k1 k2', $this->redis->sInterStore('d', array('k1', 'k2')));
		$this->assertEquals('sinterstore d k1 k2', $this->redis->sInterStore('d', 'k1', 'k2'));
	}

	public function test_SlaveOf()
	{
		$this->assertEquals('slaveof host port', $this->redis->SlaveOf('host', 'port'));
	}

	public function test_sMove()
	{
		$this->assertEquals('smove s d m', $this->redis->sMove('s', 'd', 'm'));
	}

	public function test_Sort()
	{
		$this->assertEquals('sort key r', $this->redis->Sort('key', 'r'));
	}

	public function test_StrLen()
	{
		$this->assertEquals('strlen k', $this->redis->StrLen('k'));
	}

	public function test_Subscribe()
	{
		$this->assertEquals('subscribe c', $this->redis->Subscribe('c'));
	}

	public function test_sUnion()
	{
		$this->assertEquals('sunion key', $this->redis->sUnion('key'));
	}

	public function test_sUnionStore()
	{
		$this->assertEquals('sunionstore d k', $this->redis->sUnionStore('d', 'k'));
		$this->assertEquals('sunionstore d k1 k2', $this->redis->sUnionStore('d', 'k1', 'k2'));
		$this->assertEquals('sunionstore d k1 k2', $this->redis->sUnionStore('d', array('k1', 'k2')));

	}

	public function test_Expire()
	{
		$this->assertEquals('expire k 5', $this->redis->Expire('k', 5));
	}

	public function test_TTL()
	{
		$this->assertEquals('ttl key', $this->redis->TTL('key'));
	}

	public function test_Type()
	{
		$this->assertEquals('type key', $this->redis->Type('key'));
	}

	public function test_Unsubscribe()
	{
		$this->assertEquals('unsubscribe', $this->redis->Unsubscribe());
		$this->assertEquals('unsubscribe c1', $this->redis->Unsubscribe('c1'));
		$this->assertEquals('unsubscribe c1 c2', $this->redis->Unsubscribe('c1', 'c2'));
		$this->assertEquals('unsubscribe c1 c2', $this->redis->Unsubscribe(array('c1', 'c2')));

	}

	public function test_Unwatch()
	{
		$this->assertEquals('unwatch', $this->redis->Unwatch());
	}

	public function test_zAdd()
	{
		$this->assertEquals('zadd k 101 m', $this->redis->zAdd('k', 101, 'm'));
		$this->assertEquals('zadd k 101 m 102 m2', $this->redis->zAdd('k', 101, 'm', 102, 'm2'));
		$this->assertEquals('zadd k 101 m 102 m2', $this->redis->zAdd('k', array(101 => 'm', 102 => 'm2')));
	}

	public function test_zCard()
	{
		$this->assertEquals('zcard k', $this->redis->zCard('k'));
	}

	public function test_zCount()
	{
		$this->assertEquals('zcount k 5 10', $this->redis->zCount('k', 5, 10));
	}

	public function test_zIncrBy()
	{
		$this->assertEquals('zincrby key 10 m', $this->redis->zIncrBy('key', 10, 'm'));
	}

	public function test_zinterstore()
	{
		$this->assertEquals('zinterstore d 2 a b', $this->redis->zInterStore('d', array('a', 'b')));
		$this->assertEquals('zinterstore d 2 a b weights 5 7', $this->redis->zinterstore('d', array('a', 'b'), array(5, 7)));
		$this->assertEquals('zinterstore d 2 a b aggregate max', $this->redis->zinterstore('d', array('a', 'b'), null, \Jamm\Memory\RedisServer::Aggregate_MAX));
		$this->assertEquals('zinterstore d 2 a b weights 5 7 aggregate sum', $this->redis->zinterstore('d', array('a', 'b'), array(5, 7), \Jamm\Memory\RedisServer::Aggregate_SUM));

	}

	public function test_zRange()
	{
		$this->assertEquals('zrange key 5 10', $this->redis->zRange('key', 5, 10));
		$this->assertEquals('zrange key 5 10 withscores', $this->redis->zRange('key', 5, 10, true));
	}

	public function test_zRangeByScore()
	{
		$this->assertEquals('zrangebyscore k 5 7', $this->redis->zRangeByScore('k', 5, 7));
		$this->assertEquals('zrangebyscore k 5 7 withscores', $this->redis->zRangeByScore('k', 5, 7, true));
		$this->assertEquals('zrangebyscore k 5 7 limit 1 10', $this->redis->zRangeByScore('k', 5, 7, false, array(1, 10)));
	}

	public function test_zRank()
	{
		$this->assertEquals('zrank k m', $this->redis->zRank('k', 'm'));
	}

	public function test_zRem()
	{
		$this->assertEquals('zrem k m', $this->redis->zRem('k', 'm'));
		$this->assertEquals('zrem k m m1', $this->redis->zRem('k', 'm', 'm1'));
		$this->assertEquals('zrem k m m1', $this->redis->zRem('k', array('m', 'm1')));
	}

	public function test_zRemRangeByRank()
	{
		$this->assertEquals('zremrangebyrank k 5 10', $this->redis->zRemRangeByRank('k', 5, 10));
	}

	public function test_zRemRangeByScore()
	{
		$this->assertEquals('zremrangebyscore k 5 10', $this->redis->zRemRangeByScore('k', 5, 10));
	}

	public function test_zRevRange()
	{
		$this->assertEquals('zrevrange k 5 10', $this->redis->zRevRange('k', 5, 10));
		$this->assertEquals('zrevrange k 5 10 withscores', $this->redis->zRevRange('k', 5, 10, true));
	}

	public function test_zRevRangeByScore()
	{
		$this->assertEquals('zrevrangebyscore k 5 7', $this->redis->zRevRangeByScore('k', 5, 7));
		$this->assertEquals('zrevrangebyscore k 5 7 withscores', $this->redis->zRevRangeByScore('k', 5, 7, true));
		$this->assertEquals('zrevrangebyscore k 5 7 limit 1 10', $this->redis->zRevRangeByScore('k', 5, 7, false, array(1, 10)));
	}

	public function test_zRevRank()
	{
		$this->assertEquals('zrevrank k m', $this->redis->zRevRank('k', 'm'));
	}

	public function test_zScore()
	{
		$this->assertEquals('zscore k m', $this->redis->zScore('k', 'm'));
	}

	public function test_zUnionStore()
	{
		$this->assertEquals('zunionstore d 2 a b', $this->redis->zunionStore('d', array('a', 'b')));
		$this->assertEquals('zunionstore d 2 a b weights 5 7', $this->redis->zunionStore('d', array('a', 'b'), array(5, 7)));
		$this->assertEquals('zunionstore d 2 a b aggregate max', $this->redis->zunionStore('d', array('a', 'b'), null, \Jamm\Memory\RedisServer::Aggregate_MAX));
		$this->assertEquals('zunionstore d 2 a b weights 5 7 aggregate sum', $this->redis->zunionStore('d', array('a', 'b'), array(5, 7), \Jamm\Memory\RedisServer::Aggregate_SUM));
	}

	public function test_IncrBy()
	{
		$this->assertEquals('incrby key 5', $this->redis->IncrBy('key', 5));
	}

	public function test_Keys()
	{
		$this->assertEquals('keys p*', $this->redis->Keys('p*'));
	}

	public function test_Multi()
	{
		$this->assertEquals('multi', $this->redis->multi());
	}

	public function test_Watch()
	{
		$this->assertEquals('watch', $this->redis->watch());
	}

	public function test_Exec()
	{
		$this->assertEquals('exec', $this->redis->exec());
	}

	public function test_Discard()
	{
		$this->assertEquals('discard', $this->redis->discard());
	}

	public function test_sAdd()
	{
		$this->assertEquals('sadd set v', $this->redis->sAdd('set', 'v'));
		$this->assertEquals('sadd set v v1', $this->redis->sAdd('set', 'v', 'v1'));
		$this->assertEquals('sadd set v v1', $this->redis->sAdd('set', array('v', 'v1')));
	}

	public function test_sIsMember()
	{
		$this->assertEquals('sismember s v', $this->redis->sIsMember('s', 'v'));
	}

	public function test_sMembers()
	{
		$this->assertEquals('smembers set', $this->redis->sMembers('set'));
	}

	public function test_sRem()
	{
		$this->assertEquals('srem s v', $this->redis->sRem('s', 'v'));
		$this->assertEquals('srem s v v1', $this->redis->sRem('s', 'v', 'v1'));
		$this->assertEquals('srem s v v1', $this->redis->sRem('s', array('v', 'v1')));
	}

	public function test_info()
	{
		$this->assertEquals('info', $this->redis->info());
	}
}

class MockRedisServer extends \Jamm\Memory\RedisServer
{
	protected function _send($args)
	{
		return strtolower(trim(implode(' ', $args)));
	}
}
