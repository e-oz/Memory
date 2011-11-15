<?php
namespace Jamm\Memory\Shm;

interface IMutex
{
	public function get_access_read(&$auto_unlocker_reference);

	public function release_access_read(\Jamm\Memory\IKeyLocker $autoUnlocker = NULL);

	public function get_access_write(&$auto_unlocker_reference);

	public function release_access_write(\Jamm\Memory\IKeyLocker $autoUnlocker = NULL);
}
