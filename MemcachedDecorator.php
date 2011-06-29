<?php
namespace Jamm\Memory;

class MemcachedDecorator implements IMemcacheDecorator
{
	/** @var \Memcached */
	protected $memcached;

	public function __construct($host = 'localhost', $port = 11211)
	{
		$this->memcached = new \Memcached();
		$this->memcached->addServer($host, $port);
		$this->memcached->setOption(\Memcached::OPT_COMPRESSION, true);
	}

	public function add($key, $var, $flag = null, $ttl = 0)
	{
		return $this->memcached->add($key, $var, $ttl);
	}

	public function delete($key)
	{
		return $this->memcached->delete($key);
	}

	public function get($key)
	{
		return $this->memcached->get($key);
	}

	public function increment($key, $by_value)
	{
		return $this->memcached->increment($key, $by_value);
	}

	public function set($key, $value, $flag = null, $ttl = 0)
	{
		return $this->memcached->set($key, $value, $ttl);
	}

	public function connect($host = 'localhost', $port = 11211)
	{ }

	public function getStats()
	{
		return $this->memcached->getStats();
	}
}
