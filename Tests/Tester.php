<?php
namespace Jamm\Memory\Tests;

class Tester
{
	public static function PrintResults(array $results, $newline = PHP_EOL)
	{
		/** @var TestResult $result */
		foreach ($results as $result)
		{
			print $newline.$result->getName();
			print $newline.$result->getType();
			if ($result->getDescription()!='') print $newline.$result->getDescription();
			print $newline.'Expected: ';
			var_dump($result->getExpected());
			print 'Result: ';
			var_dump($result->getResult());
			print $newline.$newline;
		}
	}

	public static function MakeTest(ITest $test)
	{
		$start_time = microtime(true);
		self::PrintResults($test->RunTests());

		print PHP_EOL.round(microtime(true)-$start_time, 6).PHP_EOL;
		print_r($test->getErrLog());
	}

}
