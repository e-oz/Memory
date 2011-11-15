<?php
namespace Jamm\Memory\Shm;

class DummyMutex implements IMutex
{

	public function get_access_read(&$auto_unlocker_reference)
	{ return true; }

	public function get_access_write(&$auto_unlocker_reference)
	{ return true; }

	public function release_access_read(\Jamm\Memory\IKeyLocker $autoUnlocker = NULL)
	{ return true; }

	public function release_access_write(\Jamm\Memory\IKeyLocker $autoUnlocker = NULL)
	{ return true; }
}
