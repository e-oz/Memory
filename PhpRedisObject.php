<?php
namespace Jamm\Memory;

class PhpRedisObject extends RedisObject
{
	protected function setDefaultRedisServer()
	{
		try
		{
			$this->redis = new \Redis();
			$this->redis->connect('127.0.0.1', '6379');
		}
		catch (\RedisException $e)
		{
			return $this->ReportError('connection error:'.$e->getMessage(), __LINE__);
		}
	}
}
