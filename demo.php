<?php

/** Ways to create the cache storage */

$mem = new \Jamm\Memory\APCObject('my_cacher');
// APC is approx. 14 time faster than Memcache, but Memcache can be used more universally, and is more flexible.
// for example, Memcache can be used with mod_php and php-cli (from cron) or other applications.

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
$mem->save('guest_login', 'Adam', 86400, array('logins'));
$mem->del_by_tags('logins');

/** Dog-pile protection */
$ttl = 0;
$value = $mem->read('mykey', $ttl);
if (!empty($value) && $ttl < 10) //if value soon will be deleted...
{
	//..then let's try to update it. But to exclude dog-pile, we should make it exclusively...
	if ($mem->lock_key('mykey', $auto_unlocker)) //...so, if I've got exclusive right to refresh this key...
	{
		//...then I will refresh this key, and nobody else with me simultaneously :)
		$value = null;
	}
}
if (empty($value))
{
	$value = 'New generated value';
	$mem->save('mykey', $value, 43200);
	//this string can be commented out - key will be safely unlocked automatically anyway
	if (isset($auto_unlocker)) $mem->unlock_key($auto_unlocker);
}
