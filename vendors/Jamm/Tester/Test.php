<?php
namespace Jamm\Tester;
class Test
{
	/** @var \Exception */
	private $exception;
	private $errors;
	/** @var Assertion[] */
	private $assertions = array();
	private $successful = true;
	private $name;

	public function getException()
	{
		return $this->exception;
	}

	public function addAssertion(Assertion $assertion)
	{
		$this->assertions[] = $assertion;
		if (!$assertion->isSuccessful()) $this->successful = false;
	}

	public function getAssertions()
	{
		return $this->assertions;
	}

	public function setException(\Exception $exception)
	{
		if (!empty($exception))
		{
			$this->exception  = $exception;
			$this->successful = false;
		}
	}

	public function hasException()
	{
		return !empty($this->exception);

	}

	/**
	 * @return Error[]
	 */
	public function getErrors()
	{
		return $this->errors;
	}

	public function setErrors($errors)
	{
		if (!empty($errors))
		{
			$this->errors     = $errors;
			$this->successful = false;
		}
	}

	public function isSuccessful()
	{
		return $this->successful;
	}

	public function setSuccessful($successful = true)
	{
		$this->successful = $successful;
	}

	public function getName()
	{
		return $this->name;
	}

	public function setName($name)
	{
		$this->name = $name;
	}
}
