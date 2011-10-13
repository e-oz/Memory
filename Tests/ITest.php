<?php
namespace Jamm\Memory\Tests;

interface ITest
{
	/**
	 * Should return array of results
	 * @return array
	 */
	public function RunTests();

	public function getErrLog();
}
