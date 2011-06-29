<?php
namespace Jamm\Memory;

class KeyAutoUnlocker
{
	public $key = '';
	protected $Unlock = NULL;

	/**
	 * @param callback $Unlock
	 */
	public function __construct($Unlock)
	{
		if (is_callable($Unlock)) $this->Unlock = $Unlock;
	}

	public function revoke()
	{
		$this->Unlock = NULL;
	}

	public function __destruct()
	{
		if (isset($this->Unlock)) call_user_func($this->Unlock, $this);
	}
}
