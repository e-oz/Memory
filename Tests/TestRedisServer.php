<?php
namespace Jamm\Memory\Tests;

/**
 * Tester::MakeTest(new TestRedisServer());
 */
class TestRedisServer implements ITest
{
	/** @var \Jamm\Memory\Tests\MockRedisServer */
	protected $redis;
	protected $results = array();

	public function __construct()
	{
		$this->redis = new MockRedisServer();
	}

	/**
	 * Should return array of results
	 * @return array
	 */
	public function RunTests()
	{
		$methods = get_class_methods(__CLASS__);
		foreach ($methods as $method)
		{
			if (strpos($method, 'test_')===0) $this->$method();
		}

		return $this->results;
	}

	public function getErrLog()
	{
		return $this->redis->getErrLog();
	}

	public function test_Append()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('append key value')->Result($this->redis->Append('key', 'value'));
	}

	public function test_Auth()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('auth pass')->Result($this->redis->Auth('pass'));
	}

	public function test_bgRewriteAOF()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('bgrewriteaof')->Result($this->redis->bgRewriteAOF());
	}

	public function test_bgSave()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('bgsave')->Result($this->redis->bgSave());
	}

	public function test_BLPop()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('blpop key1 50')->Result($this->redis->BLPop('key1', 50));

		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('blpop key1 key2 50')->Result($this->redis->BLPop('key1', 'key2', 50));

		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('blpop key1 key2 key3 50')->Result($this->redis->BLPop(array('key1', 'key2', 'key3'), 50));
	}

	public function test_BRPop()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('brpop key1 50')->Result($this->redis->brpop('key1', 50));

		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('brpop key1 key2 50')->Result($this->redis->brpop('key1', 'key2', 50));

		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('brpop key1 key2 key3 50')->Result($this->redis->brpop(array('key1', 'key2', 'key3'), 50));
	}

	public function test_BRPopLPush()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('brpoplpush source destination 50')->Result($this->redis->BRPopLPush('source', 'destination', 50));
	}

	public function test_Config_Get()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('config get pattern*')->Result($this->redis->Config_Get('pattern*'));
	}

	public function test_Config_Set()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('config set param val')->Result($this->redis->Config_Set('param', 'val'));
	}

	public function test_Config_ResetStat()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('config resetstat')->Result($this->redis->Config_ResetStat());
	}

	public function test_DBsize()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('dbsize')->Result($this->redis->DBsize());
	}

	public function test_Decr()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('decr key')->Result($this->redis->Decr('key'));
	}

	public function test_DecrBy()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('decrby key 5')->Result($this->redis->DecrBy('key', 5));
	}

	public function test_Del()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('del key')->Result($this->redis->del('key'));
	}

	public function test_Exists()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('exists key')->Result($this->redis->Exists('key'));
	}

	public function test_Expireat()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('expireat key 50')->Result($this->redis->Expireat('key', 50));
	}

	public function test_FlushAll()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('flushall')->Result($this->redis->FlushAll());
	}

	public function test_FlushDB()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('flushdb')->Result($this->redis->FlushDB());
	}

	public function test_Get()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('get key')->Result($this->redis->get('key'));
	}

	public function test_GetBit()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('getbit key 5')->Result($this->redis->GetBit('key', 5));
	}

	public function test_GetRange()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('getrange key 1 2')->Result($this->redis->GetRange('key', 1, 2));
	}

	public function test_GetSet()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('getset k v')->Result($this->redis->GetSet('k', 'v'));
	}

	public function test_hDel()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('hdel key field')->Result($this->redis->hDel('key', 'field'));
	}

	public function test_hExists()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('hexists key field')->Result($this->redis->hExists('key', 'field'));
	}

	public function test_hGet()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('hget key field')->Result($this->redis->hget('key', 'field'));
	}

	public function test_hGetAll()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected(array('h' => 'g'))->Result($this->redis->hGetAll('key'));
	}

	public function test_hIncrBy()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('hincrby key field 50')->Result($this->redis->hIncrBy('key', 'field', 50));
	}

	public function test_hKeys()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('hkeys key')->Result($this->redis->hKeys('key'));
	}

	public function test_hLen()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('hlen k')->Result($this->redis->hLen('k'));
	}

	public function test_hMGet()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('hmget key field1 field2')->Result($this->redis->hMGet('key', array('field1', 'field2')));
	}

	public function test_hMSet()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('hmset key f1 v1 f2 v2')->Result($this->redis->hMSet('key', array('f1' => 'v1', 'f2' => 'v2')));
	}

	public function test_hSet()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('hset key field value')->Result($this->redis->hSet('key', 'field', 'value'));
	}

	public function test_hSetNX()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('hsetnx key field value')->Result($this->redis->hSetNX('key', 'field', 'value'));
	}

	public function test_hVals()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('hvals key')->Result($this->redis->hVals('key'));
	}

	public function test_Incr()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('incr key')->Result($this->redis->Incr('key'));
	}

	public function test_LIndex()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('lindex key index')->Result($this->redis->LIndex('key', 'index'));
	}

	public function test_LInsert()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('linsert key after pivot value')->Result($this->redis->LInsert('key', true, 'pivot', 'value'));
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('linsert key before pivot value')->Result($this->redis->LInsert('key', false, 'pivot', 'value'));
	}

	public function test_LLen()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('llen key')->Result($this->redis->LLen('key'));
	}

	public function test_LPop()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('lpop key')->Result($this->redis->LPop('key'));
	}

	public function test_LPush()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('lpush key value')->Result($this->redis->LPush('key', 'value'));
	}

	public function test_LPushX()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('lpushx key value')->Result($this->redis->LPushX('key', 'value'));
	}

	public function test_LRange()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('lrange k 3 5')->Result($this->redis->LRange('k', 3, 5));
	}

	public function test_LRem()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('lrem key 5 value')->Result($this->redis->LRem('key', 5, 'value'));
	}

	public function test_LSet()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('lset key index value')->Result($this->redis->LSet('key', 'index', 'value'));
	}

	public function test_LTrim()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('ltrim key 5 7')->Result($this->redis->LTrim('key', 5, 7));
	}

	public function test_MGet()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('mget k')->Result($this->redis->MGet('k'));

		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('mget k1 k2')->Result($this->redis->MGet(array('k1', 'k2')));
	}

	public function test_Move()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('move key db')->Result($this->redis->Move('key', 'db'));
	}

	public function test_MSet()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('mset k v a b')->Result($this->redis->MSet(array('k' => 'v', 'a' => 'b')));
	}

	public function test_MSetNX()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('msetnx k v a b')->Result($this->redis->MSetNX(array('k' => 'v', 'a' => 'b')));
	}

	public function test_Persist()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('persist key')->Result($this->redis->Persist('key'));
	}

	public function test_PSubscribe()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('psubscribe p*')->Result($this->redis->PSubscribe('p*'));
	}

	public function test_Publish()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('publish c m')->Result($this->redis->Publish('c', 'm'));
	}

	public function test_PUnsubscribe()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('punsubscribe')->Result($this->redis->PUnsubscribe());

		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('punsubscribe p')->Result($this->redis->PUnsubscribe(array('p')));

		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('punsubscribe p1 p2')->Result($this->redis->PUnsubscribe(array('p1', 'p2')));
	}

	public function test_Quit()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('quit')->Result($this->redis->Quit());
	}

	public function test_Rename()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('rename key new')->Result($this->redis->Rename('key', 'new'));
	}

	public function test_RenameNX()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('renamenx key new')->Result($this->redis->RenameNX('key', 'new'));
	}

	public function test_RPop()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('rpop k')->Result($this->redis->RPop('k'));
	}

	public function test_RPopLPush()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('rpoplpush s d')->Result($this->redis->RPopLPush('s', 'd'));
	}

	public function test_RPush()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('rpush k v')->Result($this->redis->RPush('k', 'v'));
	}

	public function test_RPushX()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('rpushx k v')->Result($this->redis->RPushx('k', 'v'));
	}

	public function test_sCard()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('scard key')->Result($this->redis->sCard('key'));
	}

	public function test_sDiff()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('sdiff key')->Result($this->redis->sdiff('key'));
	}

	public function test_sDiffStore()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('sdiffstore d k')->Result($this->redis->sDiffStore('d', 'k'));
	}

	public function test_Select()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('select i')->Result($this->redis->Select('i'));
	}

	public function test_Set()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('set k v')->Result($this->redis->set('k', 'v'));
	}

	public function test_SetBit()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('setbit k 5 v')->Result($this->redis->SetBit('k', 5, 'v'));
	}

	public function test_SetNX()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('setnx k v')->Result($this->redis->setnx('k', 'v'));
	}

	public function test_SetEX()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('setex k 5 v')->Result($this->redis->setex('k', 5, 'v'));
	}

	public function test_SetRange()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('setrange k 5 v')->Result($this->redis->setrange('k', 5, 'v'));
	}

	public function test_sInter()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('sinter k')->Result($this->redis->sInter('k'));

		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('sinter k1 k2')->Result($this->redis->sInter('k1', 'k2'));

		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('sinter k1 k2')->Result($this->redis->sInter(array('k1', 'k2')));
	}

	public function test_sInterStore()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('sinterstore d k')->Result($this->redis->sInterStore('d', 'k'));

		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('sinterstore d k1 k2')->Result($this->redis->sInterStore('d', array('k1', 'k2')));

		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('sinterstore d k1 k2')->Result($this->redis->sInterStore('d', 'k1', 'k2'));
	}

	public function test_SlaveOf()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('slaveof host port')->Result($this->redis->SlaveOf('host', 'port'));
	}

	public function test_sMove()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('smove s d m')->Result($this->redis->sMove('s', 'd', 'm'));
	}

	public function test_Sort()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('sort key r')->Result($this->redis->Sort('key', 'r'));
	}

	public function test_StrLen()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('strlen k')->Result($this->redis->StrLen('k'));
	}

	public function test_Subscribe()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('subscribe c')->Result($this->redis->Subscribe('c'));
	}

	public function test_sUnion()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('sunion key')->Result($this->redis->sUnion('key'));
	}

	public function test_sUnionStore()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('sunionstore d k')->Result($this->redis->sUnionStore('d', 'k'));

		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('sunionstore d k1 k2')->Result($this->redis->sUnionStore('d', 'k1', 'k2'));

		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('sunionstore d k1 k2')->Result($this->redis->sUnionStore('d', array('k1', 'k2')));

	}

	public function test_Expire()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('expire k 5')->Result($this->redis->Expire('k', 5));
	}

	public function test_TTL()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('ttl key')->Result($this->redis->TTL('key'));
	}

	public function test_Type()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('type key')->Result($this->redis->Type('key'));
	}

	public function test_Unsubscribe()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('unsubscribe')->Result($this->redis->Unsubscribe());

		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('unsubscribe c1')->Result($this->redis->Unsubscribe('c1'));

		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('unsubscribe c1 c2')->Result($this->redis->Unsubscribe('c1', 'c2'));

		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('unsubscribe c1 c2')->Result($this->redis->Unsubscribe(array('c1', 'c2')));

	}

	public function test_Unwatch()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('unwatch')->Result($this->redis->Unwatch());
	}

	public function test_zAdd()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('zadd k 101 m')->Result($this->redis->zAdd('k', 101, 'm'));
	}

	public function test_zCard()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('zcard k')->Result($this->redis->zCard('k'));
	}

	public function test_zCount()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('zcount k 5 10')->Result($this->redis->zCount('k', 5, 10));
	}

	public function test_zIncrBy()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('zincrby key 10 m')->Result($this->redis->zIncrBy('key', 10, 'm'));
	}

	public function test_zinterstore()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('zinterstore d 2 a b')->Result($this->redis->zInterStore('d', array('a', 'b')));

		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('zinterstore d 2 a b weights 5 7')->Result($this->redis->zinterstore('d', array('a', 'b'), array(5, 7)));

		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('zinterstore d 2 a b aggregate max')->Result($this->redis->zinterstore('d', array('a', 'b'), null, \Jamm\Memory\RedisServer::Aggregate_MAX));

		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('zinterstore d 2 a b weights 5 7 aggregate sum')->Result($this->redis->zinterstore('d', array('a', 'b'), array(5, 7), \Jamm\Memory\RedisServer::Aggregate_SUM));

	}

	public function test_zRange()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('zrange key 5 10')->Result($this->redis->zRange('key', 5, 10));

		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('zrange key 5 10 withscores')->Result($this->redis->zRange('key', 5, 10, true));
	}

	public function test_zRangeByScore()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('zrangebyscore k 5 7')->Result($this->redis->zRangeByScore('k', 5, 7));

		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('zrangebyscore k 5 7 withscores')->Result($this->redis->zRangeByScore('k', 5, 7, true));

		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('zrangebyscore k 5 7 limit 1 10')->Result($this->redis->zRangeByScore('k', 5, 7, false, array(1, 10)));
	}

	public function test_zRank()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('zrank k m')->Result($this->redis->zRank('k', 'm'));
	}

	public function test_zRem()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('zrem k m')->Result($this->redis->zRem('k', 'm'));
	}

	public function test_zRemRangeByRank()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('zremrangebyrank k 5 10')->Result($this->redis->zRemRangeByRank('k', 5, 10));
	}

	public function test_zRemRangeByScore()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('zremrangebyscore k 5 10')->Result($this->redis->zRemRangeByScore('k', 5, 10));
	}

	public function test_zRevRange()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('zrevrange k 5 10')->Result($this->redis->zRevRange('k', 5, 10));

		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('zrevrange k 5 10 withscores')->Result($this->redis->zRevRange('k', 5, 10, true));
	}

	public function test_zRevRangeByScore()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('zrevrangebyscore k 5 7')->Result($this->redis->zRevRangeByScore('k', 5, 7));

		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('zrevrangebyscore k 5 7 withscores')->Result($this->redis->zRevRangeByScore('k', 5, 7, true));

		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('zrevrangebyscore k 5 7 limit 1 10')->Result($this->redis->zRevRangeByScore('k', 5, 7, false, array(1, 10)));
	}

	public function test_zRevRank()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('zrevrank k m')->Result($this->redis->zRevRank('k', 'm'));
	}

	public function test_zScore()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('zscore k m')->Result($this->redis->zScore('k', 'm'));
	}

	public function test_zUnionStore()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('zunionstore d 2 a b')->Result($this->redis->zunionStore('d', array('a', 'b')));

		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('zunionstore d 2 a b weights 5 7')->Result($this->redis->zunionStore('d', array('a', 'b'), array(5, 7)));

		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('zunionstore d 2 a b aggregate max')->Result($this->redis->zunionStore('d', array('a', 'b'), null, \Jamm\Memory\RedisServer::Aggregate_MAX));

		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('zunionstore d 2 a b weights 5 7 aggregate sum')->Result($this->redis->zunionStore('d', array('a', 'b'), array(5, 7), \Jamm\Memory\RedisServer::Aggregate_SUM));
	}

	public function test_IncrBy()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('incrby key 5')->Result($this->redis->IncrBy('key', 5));
	}

	public function test_Keys()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('keys p*')->Result($this->redis->Keys('p*'));
	}

	public function test_Multi()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('multi')->Result($this->redis->multi());
	}

	public function test_Watch()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('watch')->Result($this->redis->watch());
	}

	public function test_Exec()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('exec')->Result($this->redis->exec());
	}

	public function test_Discard()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('discard')->Result($this->redis->discard());
	}

	public function test_sAdd()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('sadd set v')->Result($this->redis->sAdd('set', 'v'));
	}

	public function test_sIsMember()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('sismember s v')->Result($this->redis->sIsMember('s', 'v'));
	}

	public function test_sMembers()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('smembers set')->Result($this->redis->sMembers('set'));
	}

	public function test_sRem()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('srem s v')->Result($this->redis->sRem('s', 'v'));
	}

	public function test_info()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$result->Expected('info')->Result($this->redis->info());
	}
}

class MockRedisServer extends \Jamm\Memory\RedisServer
{
	protected function _send($args)
	{
		return strtolower(trim(implode(' ', $args)));
	}
}
