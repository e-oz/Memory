<?php
namespace Jamm\Memory\Tests;

/**
 * Just call
 * Tester::MakeTest(new TestMemoryObject(new \Jamm\Memory\APCObject('test')));
 * or
 * Tester::MakeTest(new TestMemoryObject(new \Jamm\Memory\RedisObject('test')));
 */

class TestMemoryObject implements ITest
{
	protected $results = array();
	protected $mem;

	public function __construct(\Jamm\Memory\IMemoryStorage $mem)
	{
		$this->mem = $mem;
	}

	/**
	 * @return array
	 */
	public function RunTests()
	{
		$this->test_add();
		$this->test_save();
		$this->test_read();
		$this->test_del();
		$this->test_del_by_tags();
		$this->test_select_fx();
		$this->test_lock_key();
		$this->test_unlock_key();
		$this->test_increment();
		$this->test_del_old();
		$this->test_get_keys();
		$this->test_get_stat();

		return $this->results;
	}

	public function getErrLog()
	{
		return $this->mem->getErrLog();
	}

	public function test_add()
	{
		$this->results[] = $result = new TestResult(__METHOD__.' call1 (add)');
		$this->mem->del('t1');
		$call1 = $this->mem->add('t1', 1);
		$result->Expected(true)->Result($call1)->addDescription($this->mem->getLastErr());

		$this->results[] = $result = new TestResult(__METHOD__.' call2 (replace)');
		$call2 = $this->mem->add('t1', 2);
		$result->Expected(false)->Result($call2)->addDescription($this->mem->getLastErr());

		$this->results[] = $result = new TestResult(__METHOD__.' call3 (ttl)');
		$this->mem->del('t3');
		$call3 = $this->mem->add('t3', 3, 10);
		$result->Expected(true)->Result($call3)->addDescription($this->mem->getLastErr());

		$this->results[] = $result = new TestResult(__METHOD__.' call4 (tags string)');
		$this->mem->del('t4');
		$call = $this->mem->add('t4', 1, 10, 'tag');
		$result->Expected(true)->Result($call)->addDescription($this->mem->getLastErr());

		$this->results[] = $result = new TestResult(__METHOD__.' call4 (tags array)');
		$this->mem->del('t5');
		$call = $this->mem->add('t5', 1, 10, array('tag1', 'tag2'));
		$result->Expected(true)->Result($call)->addDescription($this->mem->getLastErr());
	}

	public function test_del()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$this->mem->add(__METHOD__.'d1', 1);
		$call = $this->mem->del(__METHOD__.'d1');
		$result->Expected(true)->Result($call)->addDescription($this->mem->getLastErr());
		$check = $this->mem->read(__METHOD__.'d1');
		if (!empty($check)) $result->Fail()->addDescription('variables still in cache');

		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$this->mem->add(__METHOD__.'d1', 1);
		$this->mem->add(__METHOD__.'d2', 1);
		$call = $this->mem->del(array(__METHOD__.'d1', __METHOD__.'d2'));
		$result->Expected(true)->Result($call)->addDescription($this->mem->getLastErr());
		$check = $this->mem->read(array(__METHOD__.'d1', __METHOD__.'d2'));
		if (!empty($check)) $result->Fail()->addDescription('variables still in cache')->addDescription(print_r($check, 1));
	}

	public function test_del_by_tags()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$this->mem->add(__METHOD__.'d1', 1, 10, 'tag');
		$call = $this->mem->del_by_tags('tag');
		$result->Expected(true)->Result($call)->addDescription($this->mem->getLastErr());
		$check = $this->mem->read(__METHOD__.'d1');
		if (!empty($check)) $result->Fail()->addDescription('variables still in cache');

		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$this->mem->add(__METHOD__.'d1', 1, 10, 'tag1');
		$this->mem->add(__METHOD__.'d2', 1, 10, 'tag2');
		$call = $this->mem->del_by_tags(array('tag1', 'tag2'));
		$result->Expected(true)->Result($call)->addDescription($this->mem->getLastErr());
		$check = $this->mem->read(array(__METHOD__.'d1', __METHOD__.'d2'));
		if (!empty($check)) $result->Fail()->addDescription('variables still in cache');

	}

	public function test_del_old()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$this->mem->save(__METHOD__, 11, 1);
		sleep(2);
		$call = $this->mem->del_old();
		$result->Expected(true)->Result($call)->addDescription($this->mem->getLastErr());
		$check = $this->mem->read(__METHOD__);
		if (!empty($check)) $result->Fail()->addDescription('variable still exists');
	}

	public function test_increment()
	{

		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$this->mem->save(__METHOD__, 100);
		$call = $this->mem->increment(__METHOD__, 10);
		$result->addDescription($this->mem->getLastErr());
		$check = $this->mem->read(__METHOD__);
		$result->Expected(array(110, 110))->Result(array($call, $check));

		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$call = $this->mem->increment(__METHOD__, -10);
		$result->addDescription($this->mem->getLastErr());
		$check = $this->mem->read(__METHOD__);
		$result->Expected(array(100, 100))->Result(array($call, $check));

		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$this->mem->save(__METHOD__, 'string');
		$call = $this->mem->increment(__METHOD__, 10);
		$result->addDescription($this->mem->getLastErr());
		$check = $this->mem->read(__METHOD__);
		$result->Expected(array('string10', 'string10'))->Result(array($call, $check));

		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$this->mem->save(__METHOD__, array(1, 2));
		$call = $this->mem->increment(__METHOD__, 3);
		$result->addDescription($this->mem->getLastErr());
		$check = $this->mem->read(__METHOD__);
		$result->Expected(array(array(1, 2, 3), array(1, 2, 3)))->Result(array($call, $check));

		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$this->mem->increment(__METHOD__.'inc', array('a'));
		$this->mem->increment(__METHOD__.'inc', array('b'));
		$result->addDescription($this->mem->getLastErr());
		$check = $this->mem->read(__METHOD__.'inc');
		$result->Expected(array('a', 'b'))->Result($check);

		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$this->mem->increment(__METHOD__.'inc', array('k1' => 'a'));
		$this->mem->increment(__METHOD__.'inc', array('k2' => 'b'));
		$this->mem->increment(__METHOD__.'inc', array('k2' => 'c'));
		$result->addDescription($this->mem->getLastErr());
		$check = $this->mem->read(__METHOD__.'inc');
		$result->Expected(array('a', 'b', 'k1' => 'a', 'k2' => 'c'))->Result($check);

		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$this->mem->save(__METHOD__, array(1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11));
		$call = $this->mem->increment(__METHOD__, 100, 10);
		$result->addDescription($this->mem->getLastErr());
		$check = $this->mem->read(__METHOD__);
		$result->Expected(array(array(3, 4, 5, 6, 7, 8, 9, 10, 11, 100), array(3, 4, 5, 6, 7, 8, 9, 10, 11, 100)))->Result(array($call, $check));

		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$this->mem->save(__METHOD__, 1, 10);
		$this->mem->increment(__METHOD__, 2, 0, 25);
		$check = $this->mem->read(__METHOD__, $ttl_left);
		$result->Expected(array(3, 25))->Result(array($check, $ttl_left));
	}

	public function test_lock_key()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);

		$this->mem->save(__METHOD__, 1);
		$call = $this->mem->lock_key(__METHOD__, $l);
		$result->Expected(true)->Result($call)->addDescription($this->mem->getLastErr());
		if ($call)
		{
			$check = $this->mem->lock_key(__METHOD__, $l1);
			$result->addDescription($this->mem->getLastErr());
			if ($check) $result->Fail()->addDescription('key was not locked');
			$this->mem->unlock_key($l);
			$result->addDescription($this->mem->getLastErr());
		}

	}

	public function test_read()
	{
		//Read 10
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$this->mem->save(__METHOD__.'t1', 10);
		$call = $this->mem->read(__METHOD__.'t1');
		$result->Expected(10)->Result($call)->addDescription($this->mem->getLastErr());

		//Read float key
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$key = microtime(true)*100;
		$this->mem->save($key, 10);
		$call = $this->mem->read($key);
		$result->Expected(10)->Result($call)->addDescription($this->mem->getLastErr());

		//Read negative float
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$key = -10.987;
		$this->mem->save($key, 10);
		$call = $this->mem->read($key);
		$result->Expected(10)->Result($call)->addDescription($this->mem->getLastErr());

		//Read string
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$this->mem->save(__METHOD__.'t1', 'string', 10);
		$call = $this->mem->read(__METHOD__.'t1');
		$result->setTypesCompare()->Expected('string')->Result($call)->addDescription($this->mem->getLastErr());

		//Read and check ttl
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$call = $this->mem->read(__METHOD__.'t1', $ttl_left);
		$result->Expected(array('string', 'TTL: 10'))->Result(array($call, 'TTL: '.$ttl_left))->addDescription($this->mem->getLastErr());

		//Read array and check ttl
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$this->mem->save(__METHOD__.'t11', array(10, 'string'), 100);
		$call = $this->mem->read(__METHOD__.'t11', $ttl_left);
		$result->Expected(array(array(10, 'string'), 'TTL: 100'))->Result(array($call, 'TTL: '.$ttl_left))->addDescription($this->mem->getLastErr());

	}

	public function test_save()
	{
		//Save 100
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$call = $this->mem->save(__METHOD__.'s1', 100);
		$result->Expected(true)->Result($call)->addDescription($this->mem->getLastErr());
		$check = $this->mem->read(__METHOD__.'s1');
		if ($check!=100) $result->Fail()->addDescription('value mismatch, should be 100, result: '.$check);
		$result->addDescription($this->mem->getLastErr());

		//Save 100 with ttl 10
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$call = $this->mem->save(__METHOD__.'s2', 100, 10);
		$result->Expected(true)->Result($call)->addDescription($this->mem->getLastErr());
		$check = $this->mem->read(__METHOD__.'s2', $ttl_left);
		$result->addDescription('value: '.$check);
		if ($check!=100) $result->Fail()->addDescription('value mismatch: '.$check);
		if ($ttl_left!=10) $result->Fail()->addDescription('ttl mismatch: '.$ttl_left);
		$result->addDescription($this->mem->getLastErr());

		//Save float key
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$key = microtime(true)*100;
		$call = $this->mem->save($key, 100, 12);
		$result->Expected(true)->Result($call)->addDescription($this->mem->getLastErr());
		$check = $this->mem->read($key, $ttl_left);
		$result->addDescription('ttl_left: '.$ttl_left);
		if ($check!=100) $result->Fail()->addDescription('value mismatch: '.$check);
		if ($ttl_left!=12) $result->Fail()->addDescription('ttl mismatch: '.$ttl_left);
		$result->addDescription($this->mem->getLastErr());

		//Save negative float
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$key = -10.12;
		$call = $this->mem->save($key, 100, 10);
		$result->Expected(true)->Result($call)->addDescription($this->mem->getLastErr());
		$check = $this->mem->read($key, $ttl_left);
		if ($check!=100) $result->Fail()->addDescription('value mismatch: '.$check);
		if ($ttl_left!=10) $result->Fail()->addDescription('ttl mismatch: '.$ttl_left);
		$result->addDescription($this->mem->getLastErr());

		//Save with float ttl
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$call = $this->mem->save(__METHOD__.'s21', 100, 0.000001);
		$result->Expected(true)->Result($call)->addDescription($this->mem->getLastErr());
		$check = $this->mem->read(__METHOD__.'s21', $ttl_left);
		if ($check!=100) $result->Fail()->addDescription('value mismatch: '.$check);
		if ($ttl_left < 10) $result->Fail()->addDescription('ttl mismatch: '.$ttl_left);
		$result->addDescription($this->mem->getLastErr());

		//Save with string ttl
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$call = $this->mem->save(__METHOD__.'s22', 100, 'stringttl');
		$result->Expected(true)->Result($call)->addDescription($this->mem->getLastErr());
		$check = $this->mem->read(__METHOD__.'s22', $ttl_left);
		if ($check!=100) $result->Fail()->addDescription('value mismatch: '.$check);
		$result->addDescription($this->mem->getLastErr());

		//Save with tag
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$call = $this->mem->save(__METHOD__.'s3', 100, 10, 'tag');
		$result->Expected(true)->Result($call)->addDescription($this->mem->getLastErr());
		$check = $this->mem->read(__METHOD__.'s3', $ttl_left);
		if ($check!=100) $result->Fail()->addDescription('value mismatch');
		if ($ttl_left!=10) $result->Fail()->addDescription('ttl mismatch: '.$ttl_left.' instead of 10');
		$result->addDescription($this->mem->getLastErr());

		//Save with array of tags
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$call = $this->mem->save(__METHOD__.'s4', array('z' => 1), 10, array('tag', 'tag1'));
		$result->Expected(true)->Result($call)->addDescription($this->mem->getLastErr());
		$check = $this->mem->read(__METHOD__.'s4', $ttl_left);
		if ($check!==array('z' => 1)) $result->Fail()->addDescription('value mismatch');
		if ($ttl_left!=10) $result->Fail()->addDescription('ttl mismatch: '.$ttl_left.' instead of 10');
		$result->addDescription($this->mem->getLastErr());
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

		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$call = $this->mem->select_fx(function($s, $index)
			{
				if ($index=='key1' || $s['kk2']==7) return true;
				else return false;
			});
		$result->Expected(array('kk1' => 5, 'kk2' => 7))->Result($call)->addDescription($this->mem->getLastErr());

		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$call = $this->mem->select_fx(function($s, $index)
			{
				if ($s['kk1']==$s['kk2']) return true;
				else return false;
			});
		$result->Expected(array('kk1' => 5, 'kk2' => 5))->Result($call)->addDescription($this->mem->getLastErr());

		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$call = $this->mem->select_fx(function($s, $index)
			{
				if ($s['kk1']==$s['kk2'] || $index=='key4') return true;
				else return false;
			}, true);
		$result->Expected(array('key3' => array('kk1' => 5, 'kk2' => 5), 'key4' => array('kk1' => 2, 'kk2' => 4)))->Result($call)->addDescription($this->mem->getLastErr());

		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);
		$call = $this->mem->select_fx(function($s, $index)
			{
				if ($s['kk1'] > 7 || ($s['id']==0 && $s['kk2'] < 5)) return true;
				else return false;
			}, true);
		$result->Expected(array('key4' => array('kk1' => 2, 'kk2' => 4), 'key6' => array('id' => 1, 'kk1' => 9, 'kk2' => 5), 'key7' => array('id' => 0, 'kk1' => 7, 'kk2' => 4)))->Result($call)->addDescription($this->mem->getLastErr());
	}

	public function test_unlock_key()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);

		$this->mem->save(__METHOD__, 1);
		if ($this->mem->lock_key(__METHOD__, $l))
		{
			$call = $this->mem->unlock_key($l);
			$result->Expected(true)->Result($call)->addDescription($this->mem->getLastErr());
			$check = $this->mem->lock_key(__METHOD__, $l1);
			if (!$check) $result->Fail()->addDescription('can not lock key again')->addDescription($this->mem->getLastErr());
			else $this->mem->unlock_key($l1);
		}
		else $result->Fail()->addDescription('key was not acquired')->addDescription($this->mem->getLastErr());
	}

	public function test_get_keys()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);

		$this->mem->save(__METHOD__.':1', 1);
		$this->mem->save(__METHOD__.':2', 1);
		$this->mem->save(__METHOD__.':3', 1);
		$arr = array(__METHOD__.':1', __METHOD__.':2', __METHOD__.':3');
		$call = $this->mem->get_keys();
		if (is_array($call)) $c = count($call);
		else $c = 0;
		$result->setTypesCompare()->Expected(true, $arr)->Result(is_array($call), $call)->addDescription($this->mem->getLastErr());
		$this->mem->del($call);
		$check = $this->mem->get_keys();
		$result->addDescription($this->mem->getLastErr());
		if (!empty($check)) $result->Fail()->addDescription('not all keys was deleted')->addDescription('Left: '.count($check).' from '.$c);
	}

	public function test_get_stat()
	{
		$this->results[] = $result = new TestResult(__METHOD__.__LINE__);

		$call = $this->mem->get_stat();
		$result->setTypesCompare()->Expected(true)->Result(!empty($call))->addDescription($this->mem->getLastErr());
	}
}
