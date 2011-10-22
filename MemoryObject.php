<?php
namespace Jamm\Memory;

abstract class MemoryObject implements IMemoryStorage
{
	const max_ttl = 2592000;
	const key_lock_time = 30;
	const max_wait_unlock = 0.05;

	private $last_err;
	private $err_log;
	private $errors_triggering = false;

	public function getLastErr()
	{
		$t = $this->last_err;
		$this->last_err = '';
		return $t;
	}

	protected function ReportError($msg, $line)
	{
		$error_message = $line.': '.$msg;
		$this->last_err = $error_message;
		$this->err_log[] = $error_message;
		if ($this->errors_triggering) trigger_error($error_message, E_USER_WARNING);
		return false;
	}

	public function getErrLog()
	{ return $this->err_log; }

	protected function addErrLog($err_log)
	{
		$this->err_log = array_merge($this->err_log, $err_log);
	}

	public function acquire_key($key, &$auto_unlocker)
	{
		$t = microtime(true);
		while (!$this->lock_key($key, $auto_unlocker))
		{
			if ((microtime(true)-$t) > self::max_wait_unlock) return false;
		}
		return true;
	}

	public function set_errors_triggering($errors_triggering = true)
	{
		$this->errors_triggering = $errors_triggering;
	}
}
