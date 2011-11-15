<?php
namespace Jamm\Memory;

interface IKeyLocker
{
	public function revoke();

	/** @return string */
	public function getKey();

	/** @param string $key */
	public function setKey($key);
}
