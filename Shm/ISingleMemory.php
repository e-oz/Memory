<?php
namespace Jamm\Memory\Shm;

interface ISingleMemory extends \Jamm\Memory\IMemoryStorage
{
	public function getSingleMemory();

	public function setMutex(IMutex $mutex);
}
