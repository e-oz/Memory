PHP Memory Cacher
=================
##Key-value storage in memory
As a storage can be used:

 * [APC](http://pecl.php.net/package/APC)
 * [Redis](http://redis.io)
 * [Memcache](http://pecl.php.net/package/memcache)
 * [Shared memory](http://php.net/manual/en/book.shmop.php)
 
All storage objects have one interface, so you can switch them without changing the working code.

##Features:
+ Tags for keys
+ Dog-pile and race-condition effects are excluded
+ Increment can work with arrays, strings and numeric values
+ MultiAccess class can be used for any resource, to gain access *one write multiple read*

##Usage:
See demo.php to get documentation.

##Requirements:
You can use each storage separately, requirements are individually for storages

###PHP version: 5.3+ (maybe 5.2+, not checked)

###For APCObject:
[APC](http://pecl.php.net/package/APC) should be installed, and this setting should be added in php.ini (or apc.ini if you use it)

+ apc.slam_defense = Off
+ __recommended:__ apc.user_ttl = 0

###For Memcache:
[Memcache](http://pecl.php.net/package/memcache) or [Memcached](http://pecl.php.net/package/memcached) PHP extension should be installed.  
Memcache is not the fastest and most secure storage, so use it only when necessary. [Read more](http://code.google.com/p/memcached/wiki/WhyNotMemcached)

###For Redis:
[Redis](http://redis.io) server should be installed (in debian/ubuntu: "apt-get install redis-server").
Also, [phpredis](https://github.com/nicolasff/phpredis) (if installed) can be used as client library - just use `PhpRedisObject` instead of default `RedisObject`.

###For SHMObject and MultiAccess:
PHP should support shm-functions and msg-functions (--enable-shmop --enable-sysvsem --enable-sysvshm --enable-sysvmsg)  
Should be used only in specific cases (e.g. mutexes), or when other extensions can not be installed.

#Storages comparison:
**APC** is a very fast and easy to use storage, use it in most cases.
If APC can not be used for caching data, or you just like Redis - use **Redis**.
Redis and Memcache can be used for cross-process communication. Also, data in Redis storage will be restored even after server reboot.
Don't want to install Redis (it's just 1 line in console :))? Use **Memcache**.
If you can't install any third-party packages, you can use **Shared Memory** - but your PHP should be compiled with support of shmop-functions.

##Performance comparison
+ **APC** - best performance, let speed result of APC in benchmark is 1.
+ **PhpRedis** - speed 1.23
+ **Redis** - speed 1.6
+ **Shared memory** - speed 130
+ **Memcache** - speed 192 (slowest)


TODO:
=====
+ Create Wiki pages (don't know yet what to write :))

***
_Look at the comments in demo.php for additional info. Ask, what you want to see commented._

License: [MIT](http://en.wikipedia.org/wiki/MIT_License)
