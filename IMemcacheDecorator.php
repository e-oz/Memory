<?php
namespace Jamm\Memory;

interface IMemcacheDecorator
{
	public function add($key, $var, $flag = null, $ttl = 0);

	public function get($key);

	public function delete($key);

	public function set($key, $value, $flag = null, $ttl = 0);

	public function increment($key, $by_value);

	public function connect($host = 'localhost', $port = 11211);

	public function getStats();
}
