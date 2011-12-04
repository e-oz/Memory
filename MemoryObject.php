<?php
namespace Jamm\Memory;

abstract class MemoryObject implements IMemoryStorage
{
	const max_ttl = 2592000;
	protected $key_lock_time = 30;
	protected $max_wait_unlock = 0.05;

	private $last_err;
	private $err_log;
	private $errors_triggering = true;

	public function getLastErr()
	{
		$last_err       = $this->last_err;
		$this->last_err = '';
		return $last_err;
	}

	protected function ReportError($msg, $line)
	{
		$error_message   = $line.': '.$msg;
		$this->last_err  = $error_message;
		$this->err_log[] = $error_message;
		if (count($this->err_log) > 100)
		{
			array_shift($this->err_log);
		}
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
			if ((microtime(true)-$t) > $this->max_wait_unlock) return false;
		}
		return true;
	}

	public function set_errors_triggering($errors_triggering = true)
	{
		$this->errors_triggering = $errors_triggering;
	}

	protected function incrementArray($limit_keys_count, $value, $by_value)
	{
		if ($limit_keys_count > 0 && (count($value) > $limit_keys_count)) $value = array_slice($value, $limit_keys_count*(-1)+1);

		if (is_array($by_value))
		{
			$set_key = key($by_value);
			if (!empty($set_key)) $value[$set_key] = $by_value[$set_key];
			else $value[] = $by_value[0];
		}
		else $value[] = $by_value;
		return $value;
	}

	public function set_max_wait_unlock_time($max_wait_unlock = 0.05)
	{
		$this->max_wait_unlock = $max_wait_unlock;
	}

	public function set_key_lock_time($key_lock_time = 30)
	{
		$this->key_lock_time = $key_lock_time;
	}
}
