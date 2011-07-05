<?php

$mem = new \Jamm\Memory\APCObject('my_cacher');
// APC to 192 times faster than Memcache, but Memcache can be used between processes.
// for example, Memcache can be used with mod_php and php-cli (from cron) or other applications.
// Redis can be used also between processes, and works same fast as APC.

/** Save variable: */
$mem->save('key', 'value');
//variables of any type can be stored - non-scalar will be serialized (and unserialized then)

/** Add variable: */
$mem->add('key', 'value');
//if key already exists, false will be returned - it's only difference with the 'save' method

/** Read variable: */
$var = $mem->read('key');

/** Delete variable: */
$mem->del('key');

/** Increment: */
//Increment numeric value
$mem->increment('digit', 1);
//Decrement numeric value
$mem->increment('digit', -1);
//Increment string:
$mem->save('key_s', 'abc', 10);
$mem->increment('key_s', 'defg'); //now key_s = 'abcdefg'
//Increment array:
$mem->save('log', array('start'));
$mem->increment('log', 'message'); //now 'log' = array('start','message')
$mem->increment('log', 'new message', 2); //now 'log' = array('message', 'new message')
//or without initial array:
$mem->increment('users', array('user1')); //now 'users' = array('user1')
$mem->increment('users', array('users2')); //now 'users' = array('user1', 'user2')
//and set pair key-value:
$mem->increment('users', array('admin' => 'user3')); //now 'users' = array('user1', 'user2', 'admin' => 'user3')

/** Tags */
$mem->save('user_login', 'Adam', 86400, array('users', 'logins'));
$mem->save('guest_login', 'Adam', 86400, 'logins');
$mem->del_by_tags('logins');

/** Dog-pile protection */
$value = $mem->read('mykey', $ttl_left);
if (!empty($value) && $ttl_left < 10) //if key will expire in less than 10 seconds...
{
	//then let's try to update it. But to exclude dog-pile, we should make it exclusively
	//so, if I've got exclusive right to refresh this key...
	//then I will refresh this key, and nobody else with me simultaneously :)
	if ($mem->lock_key('mykey', $auto_unlocker))
	{
		$value = null;
	}
}

if (empty($value))
{
	$value = 'New generated value';
	$mem->save('mykey', $value, 43200);
	//...
	//key will be safely unlocked automatically anyway, don't worry :)
	//...
	//but you can unlock key in any moment, if you need it:
	if (!empty($auto_unlocker)) $mem->unlock_key($auto_unlocker);
}
