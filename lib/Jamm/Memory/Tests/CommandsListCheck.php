<?php
namespace Jamm\Memory\Tests;

class CommandsListCheck
{
	private $no_diff_methods;

	/**
	 * @param $InterfaceClass
	 * @param string $json
	 * @return MethodsComparisonResults
	 */
	public function compareInterfaceAndJSON($InterfaceClass, $json = '')
	{
		$doc        = $this->getDocFromJSON($json);
		$Methods    = $this->getMethodsList($InterfaceClass);
		$DiffResult = $this->getDiff($doc, $Methods);
		return $DiffResult;
	}

	protected function getDocFromJSON($json)
	{
		if (empty($json))
		{
			$json = $this->downloadJSON();
		}
		$doc = json_decode($json, true);
		if (json_last_error() > 0)
		{
			throw new \Exception('wrong json');
		}
		return $doc;
	}

	protected function downloadJSON()
	{
		$json = file_get_contents('https://raw.github.com/antirez/redis-doc/master/commands.json');
		if (empty($json))
		{
			throw new \Exception('Can not fetch json doc');
		}
		return $json;
	}

	/**
	 * @param string|object $InterfaceClass
	 * @return \ReflectionMethod[]
	 * @throws \Exception
	 */
	protected function getMethodsList($InterfaceClass)
	{
		$Reflection = new \ReflectionClass($InterfaceClass);
		/** @var \ReflectionMethod[] $Methods */
		$Methods = $Reflection->getMethods();
		if (empty($Methods))
		{
			throw new \Exception("Can't fetch list of methods");
		}
		return $Methods;
	}

	/**
	 * @param array $doc
	 * @param \ReflectionMethod[] $Methods
	 * @return \Jamm\Memory\Tests\MethodsComparisonResults
	 */
	protected function getDiff(array $doc, array $Methods)
	{
		$Result = new MethodsComparisonResults();
		foreach ($Methods as $Method)
		{
			$method_name = $this->getUnifiedMethodName($Method->name);
			if (isset($doc[$method_name]))
			{
				if (!in_array($method_name, $this->no_diff_methods))
				{
					$args_in_method = $this->getMethodParametersList($Method);
					$args_in_doc    = $this->getArgumentsOfDocMethod($doc[$method_name], $method_name);
					$diff           = array_diff($args_in_doc, $args_in_method);
					if (!empty($diff))
					{
						$Result->diff_methods[$method_name] = array($diff, $args_in_method, $args_in_doc);
					}
				}
			}
			else
			{
				$Result->obsolete_methods[] = $Method->name;
			}
			unset($doc[$method_name]);
		}
		if (!empty($doc))
		{
			foreach ($doc as $doc_method_name => $doc_method)
			{
				$Result->new_methods[] = $doc_method_name;
			}
		}
		return $Result;
	}

	protected function getMethodParametersList(\ReflectionMethod $Method)
	{
		/** @var \ReflectionParameter[] $Parameters */
		$Parameters = $Method->getParameters();
		$arr        = array();
		foreach ($Parameters as $Parameter)
		{
			$arr[] = strtolower($Parameter->name);
		}
		return $arr;
	}

	protected function getArgumentsOfDocMethod($doc_method, $name)
	{
		if (!isset($doc_method['arguments']) || !is_array($doc_method['arguments']))
		{
			return array();
		}
		$arr = array();
		foreach ($doc_method['arguments'] as $argument)
		{
			if (is_array($argument['name']))
			{
				$argument['name'] = $argument['name'][0];
				continue;
			}
			$arr[] = str_replace('-', '_', strtolower($argument['name']));
		}
		return $arr;
	}

	public function addExcludedFromDiffMethod($method)
	{
		if (!is_array($method)) $method = array($method);
		foreach ($method as $m)
		{
			$this->no_diff_methods[] = $this->getUnifiedMethodName($m);
		}
	}

	protected function getUnifiedMethodName($method)
	{
		return trim(str_replace('_', ' ', strtoupper($method)));
	}
}