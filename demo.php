<?php

/** Ways to create cache storage */
// Static class Memory can be used very simply, without any initialization even.
// But Memory is just wrapper for concrete 'Memory Objects'.

// You can use the Memory Object directly:
$mem = new APCObject('my_cacher');
// or initialize Memory class with your object (implements IMemoryObject interface)
Memory::ini(new SHMObject(__FILE__));

/** Save variable: */
Memory::save('key', 'value');
//variables of any type can be stored - non-scalar will be serialized (and unserialized then)

/** Add variable: */
Memory::add('key', 'value');
//if key already exists, false will be returned - it's only difference with the 'save' method

/** Read variable: */
$var = Memory::read('key');

/** Delete variable: */
Memory::del('key');

/** Increment: */
//Increment numeric value
Memory::increment('digit', 1);
//Decrement numeric value
Memory::increment('digit', -1);
//Increment string:
Memory::save('key_s', 'abc', 10);
Memory::increment('key_s', 'defg'); //now key_s = 'abcdefg'
//Increment array:
Memory::save('log', array('start'));
Memory::increment('log', 'message'); //now 'log' = array('start','message')
Memory::increment('log', 'new message', 2); //now 'log' = array('message', 'new message')

/** Tags */
Memory::save('user_login', 'Adam', 86400, array('users', 'logins'));
Memory::save('guest_login', 'Adam', 86400, array('logins'));
Memory::del_by_tags('logins');

/** Dog-pile protection */
$ttl = 0;
$key = Memory::read('mykey', $ttl);
if (!empty($key) && $ttl < 10) //if value soon will be deleted...
{
	//..then let's try to update it. But to exclude dog-pile, we should make it exclusively...
	$auto_unlocker = NULL;
	if (Memory::lock_key('mykey', $auto_unlocker)) //...so, if I've got exclusive right to refresh this key...
	{
		$key = 'New generated value'; //...then I'll do it, and nobody else with me simultaneously :)
		Memory::save('mykey', $key, 43200);
		Memory::unlock_key($auto_unlocker); //this string can be commented - key will be safely unlocked automatically anyway
	}
}
