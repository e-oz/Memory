<?php

/** Save variable: */
Memory::save('key', 'value');

/** Read variable: */
$var = Memory::read('key');

/** Increment: */
//Increment numeric value
Memory::increment('digit', 1);
//Decrement numeric value
Memory::increment('digit', -1);

//Increment of string:
//key s = 'abc'
Memory::increment('s', 'defg');
//now s = 'abcdefg'

/** Tags */
Memory::save('user_login', 'Adam', array('users', 'logins'));
Memory::save('guest_login', 'Adam', array('logins'));
Memory::del_by_tags('logins');

/** Dog-pile protection */
$ttl = 0;
$key = Memory::read('mykey', $ttl);
if (!empty($key) && $ttl < 10) //if value soon will be deleted...
{
	$auto_unlocker = NULL;
	if (Memory::lock_key('mykey', $auto_unlocker)) //and I've got exclusive right to refresh this key...
	{
		$key = 'New generated value'; //then I'll do it, and nobody else with me simultaneously :)
		Memory::save('mykey', $key, 43200); //43200 - ttl in seconds, 12 hours
		Memory::unlock_key($auto_unlocker); //this string can be commented - key will be safely unlocked automatically anyway
	}
}


