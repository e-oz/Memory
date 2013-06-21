<?php
namespace Jamm\Tester;

class Assertion
{
	protected $expected_result;
	protected $actual_result;
	protected $commentary;
	protected $name;
	protected $successful = true;
	protected $debug_line;
	protected $debug_file;
	protected $debug_method;
	protected $debug_trace_level = 4;

	public function __construct($name = '')
	{
		$this->parseDebugInfo();
		if (empty($name))
		{
			$name = $this->debug_method;
		}
		$this->name = $name;
	}

	public function setActualResult($actual_result)
	{
		$this->actual_result = $actual_result;
	}

	public function getActualResult()
	{
		return $this->actual_result;
	}

	public function addCommentary($commentary)
	{
		if (!is_scalar($commentary))
		{
			$commentary = print_r($commentary, 1);
		}
		if (!empty($this->commentary))
		{
			$this->commentary .= PHP_EOL;
		}
		$this->commentary .= $commentary;
		return $this;
	}

	public function getCommentary()
	{
		return $this->commentary;
	}

	public function setExpectedResult($expected_result)
	{
		$this->expected_result = $expected_result;
	}

	public function getExpectedResult()
	{
		return $this->expected_result;
	}

	public function setName($name)
	{
		$this->name = $name;
	}

	public function getName()
	{
		return $this->name;
	}

	private function parseDebugInfo()
	{
		$level              = $this->debug_trace_level;
		$debug_info         = debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS);
		$this->debug_line   = $debug_info[$level]['line'];
		$this->debug_method = $debug_info[$level]['function'];
		$this->debug_file   = $debug_info[$level]['file'];
	}

	public function getDebugFile()
	{
		return $this->debug_file;
	}

	public function getDebugLine()
	{
		return $this->debug_line;
	}

	public function getDebugMethod()
	{
		return $this->debug_method;
	}

	public function Assert($successful = true)
	{
		$this->successful = $successful;
	}

	public function isSuccessful()
	{
		return $this->successful;
	}

	public function setDebugTraceLevel($debug_trace_level)
	{
		$this->debug_trace_level = $debug_trace_level;
	}
}
