<?php
namespace Jamm\Memory\Tests;

class TestMemoryObject extends \Jamm\Tester\ClassTest
{
	protected $results = array();
	protected $mem;

	public function __construct(\Jamm\Memory\IMemoryStorage $mem)
	{
		$this->mem = $mem;
		$this->mem->set_errors_triggering(true);
	}

	public function getErrLog()
	{
		return $this->mem->getErrLog();
	}

	public function test_add()
	{
		$this->mem->del('t1');
		$call1 = $this->mem->add('t1', 1);
		$this->assertTrue($call1);

		$call2 = $this->mem->add('t1', 2);
		$this->assertTrue(!$call2);

		$this->mem->del('t3');
		$call3 = $this->mem->add('t3', 3, 10);
		$this->assertTrue($call3);

		$this->mem->del('t4');
		$call = $this->mem->add('t4', 1, 10, 'tag');
		$this->assertTrue($call);

		$this->mem->del('t5');
		$call = $this->mem->add('t5', 1, 10, array('tag1', 'tag2'));
		$this->assertTrue($call);
	}

	public function test_del()
	{
		$this->mem->add(__METHOD__.'d1', 1);
		$call = $this->mem->del(__METHOD__.'d1');
		$this->assertTrue($call);
		$check = $this->mem->read(__METHOD__.'d1');
		$this->assertTrue(empty($check))->addCommentary('variables still in cache');

		$this->mem->add(__METHOD__.'d1', 1);
		$this->mem->add(__METHOD__.'d2', 1);
		$call = $this->mem->del(array(__METHOD__.'d1', __METHOD__.'d2'));
		$this->assertTrue($call);
		$check = $this->mem->read(array(__METHOD__.'d1', __METHOD__.'d2'));
		$this->assertTrue(empty($check))->addCommentary('variables still in cache');
	}

	public function test_del_by_tags()
	{
		$this->mem->add(__METHOD__.'d1', 1, 10, 'tag');
		$call = $this->mem->del_by_tags('tag');
		$this->assertTrue($call);
		$check = $this->mem->read(__METHOD__.'d1');
		$this->assertTrue(empty($check))->addCommentary('variables still in cache');

		$this->mem->add(__METHOD__.'d1', 1, 10, 'tag1');
		$this->mem->add(__METHOD__.'d2', 1, 10, 'tag2');
		$call = $this->mem->del_by_tags(array('tag1', 'tag2'));
		$this->assertTrue($call);
		$check = $this->mem->read(array(__METHOD__.'d1', __METHOD__.'d2'));
		$this->assertTrue(empty($check))->addCommentary('variables still in cache');
	}

	public function test_del_old()
	{
		$this->mem->save(__METHOD__, 11, 1);
		sleep(2);
		$call = $this->mem->del_old();
		$this->assertTrue($call);
		$check = $this->mem->read(__METHOD__);
		$this->assertTrue(empty($check))->addCommentary('variable still exists');
	}

	public function test_increment()
	{
		$this->mem->save(__METHOD__, 100);
		$call = $this->mem->increment(__METHOD__, 10);
		$this->assertEquals($call, 110);
		$check = $this->mem->read(__METHOD__);
		$this->assertEquals($check, 110);

		$call = $this->mem->increment(__METHOD__, -10);
		$this->assertEquals($call, 100);
		$check = $this->mem->read(__METHOD__);
		$this->assertEquals($check, 100);

		$this->mem->save(__METHOD__, 'string');
		$call = $this->mem->increment(__METHOD__, 10);
		$this->assertEquals($call, 'string10');
		$check = $this->mem->read(__METHOD__);
		$this->assertEquals($check, 'string10');

		$this->mem->save(__METHOD__, array(1, 2));
		$this->assertEquals($this->mem->increment(__METHOD__, 3), array(1, 2, 3));
		$this->assertEquals($this->mem->read(__METHOD__), array(1, 2, 3));

		$this->mem->increment(__METHOD__.'inc', array('a'));
		$this->mem->increment(__METHOD__.'inc', array('b'));
		$this->assertEquals($this->mem->read(__METHOD__.'inc'), array('a', 'b'));

		$this->mem->increment(__METHOD__.'inc', array('k1' => 'a'));
		$this->mem->increment(__METHOD__.'inc', array('k2' => 'b'));
		$this->mem->increment(__METHOD__.'inc', array('k2' => 'c'));
		$this->assertEquals($this->mem->read(__METHOD__.'inc'),
			array('a', 'b', 'k1' => 'a', 'k2' => 'c'));

		$this->mem->save(__METHOD__, array(1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11));
		$call = $this->mem->increment(__METHOD__, 100, 10);
		$this->assertEquals($call, array(3, 4, 5, 6, 7, 8, 9, 10, 11, 100));
		$check = $this->mem->read(__METHOD__);
		$this->assertEquals($check, array(3, 4, 5, 6, 7, 8, 9, 10, 11, 100));

		$this->mem->save(__METHOD__, 1, 10);
		$this->mem->increment(__METHOD__, 2, 0, 25);
		$check = $this->mem->read(__METHOD__, $ttl_left);
		$this->assertEquals($check, 3);
		$this->assertEquals($ttl_left, 25)->addCommentary('ttl_left');
	}

	public function test_lock_key()
	{
		$this->mem->save(__METHOD__, 1);
		$call = $this->mem->lock_key(__METHOD__, $l);
		$this->assertTrue($call);
		if ($call)
		{
			$check = $this->mem->lock_key(__METHOD__, $l1);
			$this->assertTrue(!$check)->addCommentary('key was not locked');
			$this->mem->unlock_key($l);
		}
	}

	public function test_read()
	{
		//Read 10
		$this->mem->save(__METHOD__.'t1', 10);
		$this->assertEquals($this->mem->read(__METHOD__.'t1'), 10);

		//Read float key
		$key = microtime(true)*100;
		$this->mem->save($key, 10);
		$this->assertEquals($this->mem->read($key), 10);

		//Read negative float
		$key = -10.987;
		$this->mem->save($key, 10);
		$this->assertEquals($this->mem->read($key), 10);

		//Read string
		$this->mem->save(__METHOD__.'t1', 'string', 10);
		$this->assertEquals($this->mem->read(__METHOD__.'t1'), 'string');

		//Read and check ttl
		$call = $this->mem->read(__METHOD__.'t1', $ttl_left);
		$this->assertEquals($call, 'string');
		$this->assertEquals($ttl_left, 10)->addCommentary('ttl');

		//Read array and check ttl
		$this->mem->save(__METHOD__.'t11', array(10, 'string'), 100);
		$call = $this->mem->read(__METHOD__.'t11', $ttl_left);
		$this->assertEquals($call, array(10, 'string'));
		$this->assertEquals($ttl_left, 100)->addCommentary('ttl');
	}

	public function test_save()
	{
		//Save 100
		$call = $this->mem->save(__METHOD__.'s1', 100);
		$this->assertTrue($call);
		$this->assertEquals($this->mem->read(__METHOD__.'s1'), 100);

		//Save 100 with ttl 10
		$this->assertTrue($this->mem->save(__METHOD__.'s2', 100, 10));
		$this->assertEquals($this->mem->read(__METHOD__.'s2', $ttl_left), 100);
		$this->assertEquals($ttl_left, 10);

		//Save float key
		$key = microtime(true)*100;
		$this->assertTrue($this->mem->save($key, 100, 12));
		$check = $this->mem->read($key, $ttl_left);
		$this->assertEquals($check, 100);
		$this->assertEquals($ttl_left, 12);

		//Save negative float key
		$key = -10.12;
		$this->assertTrue($this->mem->save($key, 100, 10));
		$check = $this->mem->read($key, $ttl_left);
		$this->assertEquals($check, 100);
		$this->assertEquals($ttl_left, 10);

		//Save with float ttl
		$call = $this->mem->save(__METHOD__.'s21', 100, 0.000001);
		$this->assertTrue($call);
		$check = $this->mem->read(__METHOD__.'s21', $ttl_left);
		$this->assertEquals($check, 100);
		$this->assertTrue($ttl_left > 10)->addCommentary('ttl mismatch: '.$ttl_left);

		//Save with string ttl
		$call = $this->mem->save(__METHOD__.'s22', 100, 'stringttl');
		$this->assertTrue($call);
		$check = $this->mem->read(__METHOD__.'s22', $ttl_left);
		$this->assertEquals($check, 100);

		//Save with tag
		$call = $this->mem->save(__METHOD__.'s3', 100, 10, 'tag');
		$this->assertTrue($call);
		$check = $this->mem->read(__METHOD__.'s3', $ttl_left);
		$this->assertEquals($check, 100);
		$this->assertEquals($ttl_left, 10);

		//Save with array of tags
		$call = $this->mem->save(__METHOD__.'s4', array('z' => 1), 10, array('tag', 'tag1'));
		$this->assertTrue($call);
		$check = $this->mem->read(__METHOD__.'s4', $ttl_left);
		$this->assertEquals($check, array('z' => 1));
		$this->assertEquals($ttl_left, 10);

	}

	public function test_select_fx()
	{
		$this->mem->del($this->mem->get_keys());
		$this->mem->save('key1', array('kk1' => 5, 'kk2' => 7));
		$this->mem->save('key2', array('kk1' => 4, 'kk2' => 6));
		$this->mem->save('key3', array('kk1' => 5, 'kk2' => 5));
		$this->mem->save('key4', array('kk1' => 2, 'kk2' => 4));
		$this->mem->save('key5', array('id' => 0, 'kk1' => 6, 'kk2' => 5));
		$this->mem->save('key6', array('id' => 1, 'kk1' => 9, 'kk2' => 5));
		$this->mem->save('key7', array('id' => 0, 'kk1' => 7, 'kk2' => 4));

		$call = $this->mem->select_fx(function($s, $index)
		{
			if ($index=='key1' || $s['kk2']==7) return true;
			else return false;
		});
		$this->assertEquals($call, array('kk1' => 5, 'kk2' => 7));

		$call = $this->mem->select_fx(function($s, $index)
		{
			if ($s['kk1']==$s['kk2']) return true;
			else return false;
		});
		$this->assertEquals($call, array('kk1' => 5, 'kk2' => 5));

		$call = $this->mem->select_fx(function($s, $index)
		{
			if ($s['kk1']==$s['kk2'] || $index=='key4') return true;
			else return false;
		}, true);
		$this->assertEquals($call, array('key3' => array('kk1' => 5, 'kk2' => 5), 'key4' => array('kk1' => 2, 'kk2' => 4)));

		$call = $this->mem->select_fx(function($s, $index)
		{
			if ($s['kk1'] > 7 || ($s['id']==0 && $s['kk2'] < 5)) return true;
			else return false;
		}, true);
		$this->assertEquals($call, array('key4' => array('kk1' => 2, 'kk2' => 4), 'key6' => array('id' => 1, 'kk1' => 9, 'kk2' => 5), 'key7' => array('id' => 0, 'kk1' => 7, 'kk2' => 4)));
	}

	public function test_unlock_key()
	{
		$this->mem->save(__METHOD__, 1);
		$lock = $this->mem->lock_key(__METHOD__, $l);
		$this->assertTrue($lock);
		if ($lock)
		{
			$call = $this->mem->unlock_key($l);
			$this->assertTrue($call);
			$check = $this->mem->lock_key(__METHOD__, $l1);
			$this->assertTrue($check)->addCommentary('can not lock key again');
			$this->mem->unlock_key($l1);
		}
	}

	public function test_get_keys()
	{
		$this->mem->del($this->mem->get_keys());
		$this->mem->save(__METHOD__.':1', 1);
		$this->mem->save(__METHOD__.':2', 1);
		$this->mem->save(__METHOD__.':3', 1);
		$arr = array(__METHOD__.':1', __METHOD__.':2', __METHOD__.':3');
		$call = $this->mem->get_keys();
		if (is_array($call)) $c = count($call);
		else $c = 0;
		$this->assertEquals($call, $arr);
		$this->mem->del($call);
		$check = $this->mem->get_keys();
		$this->assertTrue(empty($check))->addCommentary('not all keys was deleted, Left: '.count($check).' from '.$c);
	}

	public function test_get_stat()
	{
		$call = $this->mem->get_stat();
		$this->assertTrue(!empty($call));
	}
}
