<?php
include __DIR__.'/../vendors/Jamm/Autoload/Autoloader.php';
$Autoloader = new Jamm\Autoload\Autoloader(false);
$Autoloader->set_modules_dir(__DIR__.'/../vendors');
$Autoloader->register_namespace_dir('Jamm\\Memory', __DIR__.'/../lib/Jamm/Memory');
$Autoloader->start();

$RedisServer = new \Jamm\Memory\RedisServer();
$RedisServer->FlushAll();
$Storage = new \Jamm\Memory\RedisObject('Travis', $RedisServer);
$Test    = new \Jamm\Memory\Tests\TestMemoryObject($Storage);
$Printer = new \Jamm\Tester\ResultsPrinter();
$Test->RunTests();
/** @var \Jamm\Tester\Test[] $tests */
$tests = $Test->getTests();
$RedisServer->FlushAll();
$TestRedisServer = new \Jamm\Memory\Tests\TestRedisServer($RedisServer);
$TestRedisServer->RunTests();
$tests = array_merge($tests, $Test->getTests());

$Printer->addTests($tests);
$Printer->printResultsLine();

foreach ($tests as $test_result)
{
	if (!$test_result->isSuccessful())
	{
		$Printer->printFailedTests();
		exit(1);
	}
}
exit(0);
