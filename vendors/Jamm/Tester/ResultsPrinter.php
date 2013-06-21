<?php
namespace Jamm\Tester;
class ResultsPrinter
{
	private $newline = "\n";
	private $line_separator = "_______________";
	/** @var Test[] */
	private $tests = array();
	private $print_errors_traces = true;

	public function __construct(array $tests = null)
	{
		if (!empty($tests)) $this->tests = $tests;
	}

	public function addTests(array $tests)
	{
		if (!empty($this->tests) && is_array($this->tests))
		{
			$this->tests = array_merge($this->tests, $tests);
		}
		else
		{
			$this->tests = $tests;
		}
	}

	public function printFailedTests()
	{
		$br                   = $this->newline;
		$all_tests_successful = true;
		foreach ($this->tests as $test)
		{
			if (!$test->isSuccessful())
			{
				print $br.'Test '.$test->getName().' is failed.';
				$all_tests_successful = false;
				foreach ($test->getAssertions() as $assertion)
				{
					if (!$assertion->isSuccessful())
					{
						print $br.'Assertion '.$assertion->getName().' is failed.'.$br.'Expected result: ';
						var_dump($assertion->getExpectedResult());
						print 'Actual result: ';
						var_dump($assertion->getActualResult());
						$commentary = $assertion->getCommentary();
						if (!empty($commentary)) print 'Commentary: '.$commentary.$br;
						print 'Method '.$assertion->getDebugMethod().', line: '.$assertion->getDebugLine().', file: '.$assertion->getDebugFile().$br.$this->line_separator.$br;
					}
				}
			}
			if ($test->hasException())
			{
				$exception = $test->getException();
				print $br.'Exception in test '.$test->getName().': '
						.$br.'Message: '.$exception->getMessage()
						.$br.'Code: '.$exception->getCode()
						.$br.'File: '.$exception->getFile()
						.$br.'Line: '.$exception->getLine()
						.$br.'Trace: '.$exception->getTraceAsString()
						.$br;
			}
			$errors = $test->getErrors();
			if (!empty($errors))
			{
				print $br.'Errors in test '.$test->getName().': ';
				foreach ($errors as $error)
				{
					print $br.'Error '.$error->getCode().': "'.$error->getMessage().'" in file '.$error->getFilepath().', line '.$error->getLine();
					if ($this->print_errors_traces && ($trace = $error->getDebugTrace()))
					{
						print 'Trace: '.$br;
						print_r($trace);
						print $br;
					}
				}
			}
		}
		if ($all_tests_successful) print $br.'All '.count($this->tests).' tests are successful'.$br;
	}

	public function printResultsLine()
	{
		print $this->newline;
		$assertions = 0;
		foreach ($this->tests as $test)
		{
			$assertions += count($test->getAssertions());
			if ($test->isSuccessful())
			{
				print '.';
			}
			else
			{
				print 'F';
			}
		}
		print $this->newline;
		print PHP_EOL.count($this->tests).' tests, '.$assertions.' assertions';
	}

	public function setNewlineSeparator($newline)
	{
		$this->newline = $newline;
	}

	public function setLineSeparator($line_separator)
	{
		$this->line_separator = $line_separator;
	}

	public function setPrintErrorsTraces($print_errors_traces = true)
	{
		$this->print_errors_traces = $print_errors_traces;
	}

	/**
	 * Will return 1 if at least one of tests is not passed, 0 if all tests are successful
	 * @param bool $print_failed_tests
	 * @return int
	 */
	public function getExitStatusCode($print_failed_tests = true)
	{
		foreach ($this->tests as $test_result)
		{
			if (!$test_result->isSuccessful())
			{
				if ($print_failed_tests)
				{
					$this->printFailedTests();
				}
				return 1;
			}
		}
		return 0;
	}

	public function printAndExit()
	{
		$this->printResultsLine();
		exit($this->getExitStatusCode(true));
	}
}
