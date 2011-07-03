<?php
namespace Jamm\Memory\Tests;

class TestResult
{
	protected $name;
	protected $type;
	protected $expected;
	protected $result;
	protected $expected_setted = false;
	protected $types_compare = false;
	protected $description;

	const type_success = 'success';
	const type_fail = 'fail';

	public function __construct($name)
	{
		$this->name = $name;
		$this->Success();
		return $this;
	}

	public function Expected($expected)
	{
		$this->expected = $expected;
		$this->expected_setted = true;
		return $this;
	}

	public function Result($result)
	{
		$this->result = $result;
		if ($this->expected_setted)
		{
			if ($this->types_compare)
			{
				if ($result===$this->expected) $this->Success();
				else $this->Fail();
			}
			else
			{
				if ($result==$this->expected) $this->Success();
				else $this->Fail();
			}
		}
		return $this;
	}

	public function getExpected()
	{ return $this->expected; }

	public function setName($name)
	{
		$this->name = $name;
		return $this;
	}

	public function getName()
	{ return $this->name; }

	public function getResult()
	{ return $this->result; }

	public function Success()
	{
		$this->type = self::type_success;
		return $this;
	}

	public function Fail()
	{
		$this->type = self::type_fail;
		return $this;
	}

	public function getType()
	{ return $this->type; }

	public function setTypesCompare($types_compare = true)
	{
		if (is_bool($types_compare)) $this->types_compare = $types_compare;
		return $this;
	}

	public function getTypesCompare()
	{ return $this->types_compare; }

	public function addDescription($description)
	{
		if (empty($description)) return false;
		if (!empty($this->description)) $this->description .= PHP_EOL.$description;
		else $this->description = $description;
		return $this;
	}

	public function getDescription()
	{ return $this->description; }

}
