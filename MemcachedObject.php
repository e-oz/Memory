<?php
namespace Jamm\Memory;

class MemcachedObject extends MemcacheObject
{
	protected function setMemcacheObject($host = 'localhost', $port = 11211)
	{
		$this->memcache = new MemcachedDecorator($host, $port);
	}
}
