<?php
namespace Jamm\Memory;

abstract class MemoryObject
{
	const max_ttl = 2592000;
	const key_lock_time = 30;

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
}
