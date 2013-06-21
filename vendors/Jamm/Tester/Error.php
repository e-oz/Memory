<?php
namespace Jamm\Tester;

class Error
{
	protected $message;
	protected $code;
	protected $filepath;
	protected $line;
	protected $timestamp;
	protected $debug_trace;
	protected $debug_trace_level = 4;

	public function __construct()
	{
		$this->setInitialTime();
		$trace             = debug_backtrace();
		$this->debug_trace = array_slice($trace, $this->debug_trace_level);
	}

	public function setInitialTime()
	{
		$current_timezone = date_default_timezone_get();
		$php_ini_timezone = ini_get('date.timezone');
		if (!empty($php_ini_timezone) && $current_timezone!==$php_ini_timezone)
		{
			date_default_timezone_set($php_ini_timezone);
			$this->timestamp = date('d.m H:i:s');
			date_default_timezone_set($current_timezone);
		}
		else
		{
			$this->timestamp = date('d.m H:i:s');
		}
	}

	public function setCode($code)
	{
		$this->code = $code;
	}

	public function getCode()
	{
		return $this->code;
	}

	public function setDebugTrace($debug_trace)
	{
		$this->debug_trace = $debug_trace;
	}

	public function getDebugTrace()
	{
		return $this->debug_trace;
	}

	public function setFilepath($filepath)
	{
		$this->filepath = $filepath;
	}

	public function getFilepath()
	{
		return $this->filepath;
	}

	public function setLine($line)
	{
		$this->line = $line;
	}

	public function getLine()
	{
		return $this->line;
	}

	public function setMessage($message)
	{
		$this->message = $message;
	}

	public function getMessage()
	{
		return $this->message;
	}

	public function setTimestamp($timestamp)
	{
		$this->timestamp = $timestamp;
	}

	public function getTimestamp()
	{
		return $this->timestamp;
	}
}
