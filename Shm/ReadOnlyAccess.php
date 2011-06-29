<?php
namespace Jamm\Memory\Shm;

class ReadOnlyAccess extends MultiAccess
{
	public function get_access_write(&$auto_unlocker_reference)
	{ return false; }
}
