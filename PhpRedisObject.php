<?php
namespace Jamm\Memory;
class PhpRedisObject extends RedisObject
{
	protected function setDefaultRedisServer()
	{
		try
		{
			/** @noinspection PhpUndefinedClassInspection */
			$this->redis = new \Redis();
			/** @noinspection PhpUndefinedMethodInspection */
			$this->redis->connect('127.0.0.1', '6379');
		}
			/** @noinspection PhpUndefinedClassInspection */
		catch (\RedisException $e)
		{
			/** @noinspection PhpUndefinedMethodInspection */
			return $this->ReportError('connection error:'.$e->getMessage(), __LINE__);
		}
	}
}
