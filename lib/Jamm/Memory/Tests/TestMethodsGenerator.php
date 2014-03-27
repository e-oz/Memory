<?php
namespace Jamm\Memory\Tests;

class TestMethodsGenerator
{
	/**
	 * @param string|object $Interface
	 * @param string|object $ExistingMethodsInterface
	 * @return string
	 */
	public function getTestMethodsForClass($Interface, $ExistingMethodsInterface = '')
	{
		$Reflection = new \ReflectionClass($Interface);
		$Methods    = $Reflection->getMethods();
		$existing   = array();
		if (!empty($ExistingMethodsInterface))
		{
			$ExistingClass   = new \ReflectionClass($ExistingMethodsInterface);
			$ExistingMethods = $ExistingClass->getMethods();
			if (!empty($ExistingMethods))
			{
				foreach ($ExistingMethods as $ExistingMethod)
				{
					$name            = strtolower($ExistingMethod->name);
					$name            = str_replace('test_', '', $name);
					$name            = str_replace('test', '', $name);
					$existing[$name] = 1;
				}
			}
		}
		$code = '';
		foreach ($Methods as $Method)
		{
			if ($Method->isPublic())
			{
				$name = strtolower($Method->name);
				if (isset($existing[$name])) continue;
				$code .= $this->getCodeForMethod($Method).PHP_EOL;
			}
		}
		return $code;
	}

	protected function getCodeForMethod(\ReflectionMethod $Method)
	{
		$code      = "\tpublic function test_".$Method->name.'()'.PHP_EOL.
				"\t{\n";
		$arguments = $Method->getParameters();
		if (count($arguments)==0)
		{
			$code .= "\t\t".'$this->assertEquals("'.strtolower(str_replace('_', ' ', $Method->name)).'", $this->redis->'.$Method->name.'());'.PHP_EOL;
		}
		else
		{
			$code .= "\t\t//TODO: implement test\n";
			$params = array();
			foreach ($arguments as $Argument)
			{
				$params[] = strtolower($Argument->name);
			}
			$code .= "\t\t".'$this->assertEquals("'.strtolower(str_replace('_', ' ', $Method->name)).' ';
			$code .= implode(' ', $params);
			$code .= '", $this->redis->'.$Method->name.'("'.implode('", "', $params).'"));'.PHP_EOL;
		}
		$code .= "\t}\n";
		return $code;
	}
}
