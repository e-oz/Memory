<?php
namespace Jamm\Tester;
class ErrorCatcher
{
	private $handler_changed = false;
	/** @var Error[] */
	private $errors = array();
	private $errors_types = E_ALL;
	/** @var DebugTracer */
	private $debug_tracer;

	public function __construct()
	{
		$this->errors_types = E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED;
	}

	public function setUp()
	{
		if (set_error_handler(array($this, 'errorHandler'), $this->errors_types))
		{
			$this->handler_changed = true;
			return true;
		}
		else return false;
	}

	public function getErrors()
	{
		return $this->errors;
	}

	/**
	 * @return Error
	 */
	protected function getNewErrorObject()
	{
		/** Cake is a lie */
		return new Error();
	}

	public function errorHandler($error_code, $error_message, $filepath = '', $line = 0)
	{
		$error = $this->getNewErrorObject();
		$error->setCode($error_code);
		$error->setMessage($error_message);
		$error->setFilepath($filepath);
		$error->setLine($line);
		$error->setDebugTrace($this->getCurrentBacktrace());
		$this->errors[] = $error;
	}

	private function getCurrentBacktrace()
	{
		if (empty($this->debug_tracer)) $this->debug_tracer = new DebugTracer();
		return $this->debug_tracer->getCurrentBacktrace();
	}

	public function __destruct()
	{
		if ($this->handler_changed) restore_error_handler();
	}

	public function hasErrors()
	{
		return !empty($this->errors);
	}

	public function setHandledErrorsTypes($errors_types)
	{
		$this->errors_types = $errors_types;
	}

}
