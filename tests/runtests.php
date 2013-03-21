<?php
include __DIR__.'/../vendors/Autoload/lib/Jamm/Autoload/Autoloader.php';
$Autoloader = new Jamm\Autoload\Autoloader(false);
$Autoloader->set_modules_dir(__DIR__.'/../');
$Autoloader->register_namespace_dir('Jamm\\Tester', __DIR__.'/../vendors/Jamm/Tester/');
$Autoloader->start();

$Storage = new \Jamm\Memory\APCObject();
$Test    = new \Jamm\Memory\Tests\TestMemoryObject($Storage);
$Test->RunTests();
foreach ($Test->getTests() as $test_result)
{
	if (!$test_result->isSuccessful())
	{
		exit(1);
	}
}
exit(0);
