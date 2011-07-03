<?php
namespace Jamm\Memory;

abstract class MemoryObject implements IMemoryStorage
{
	const max_ttl = 2592000;
	const key_lock_time = 30;
	const max_wait_unlock = 0.05;

	protected $last_err;
	protected $err_log;

	public function getLastErr()
	{
		$t = $this->last_err;
		$this->last_err = '';
		return $t;
	}

	protected function ReportError($msg, $line)
	{
		$this->last_err = $line.': '.$msg;
		$this->err_log[] = $line.': '.$msg;
		return false;
	}

	public function getErrLog()
	{ return $this->err_log; }

	public function acquire_key($key, &$auto_unlocker)
	{
		$t = microtime(true);
		while (!$this->lock_key($key, $auto_unlocker))
		{
			if ((microtime(true)-$t) > self::max_wait_unlock) return false;
		}
		return true;
	}
}
