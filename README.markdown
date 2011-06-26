PHP Memory Cacher
=================
##Key-value storage in memory
As a storage can be used APC, Memcache or shared memory.
All storage objects have one interface, so you can switch them without changing the working code.

###Features:
+ Tags for keys
+ Dog-pile and race-condition effects are excluded
+ Increment can work with arrays, strings and numeric values
+ MultiAccess class can be used for any resource, to gain access *one write multiple read*

###Usage:
For basic usage, download common.php and see demo.php to get documentation.

###Requirements:
####PHP version: 5.3+

You can use each storage separately, requirements are individually for storages

####For APCObject:
[APC](http://pecl.php.net/package/APC) should be installed, and this setting should be added in php.ini (or apc.ini if you use it)

+ apc.slam_defense = Off
+ __recommended:__ apc.user_ttl = 0

APC is a very fast and easy to use storage, use it in most cases.

####For Memcache:
[Memcache](http://pecl.php.net/package/memcache) or [Memcached](http://pecl.php.net/package/memcached) PHP extension should be installed.  
Memcache is not the fastest and most secure storage, so use it only when necessary. [Read more](http://code.google.com/p/memcached/wiki/WhyNotMemcached)

####For SHMObject and MultiAccess:
PHP should support shm-functions and msg-functions (--enable-shmop --enable-sysvsem --enable-sysvshm --enable-sysvmsg)  
Should be used only in specific cases (e.g. mutexes), or when other extensions can not be installed.

TODO:
=====
+ Add Redis storage

***
_Look at the comments in demo.php for additional info. Ask, what you want to see commented._

License: [MIT](http://en.wikipedia.org/wiki/MIT_License)
