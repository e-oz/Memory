<?php

class TestResult
{
	protected $name;
	protected $type;
	protected $expected;
	protected $result;
	protected $expected_setted = false;
	protected $types_compare = false;
	protected $description;

	const type_success = 'success';
	const type_fail = 'fail';

	public function __construct($name)
	{
		$this->name = $name;
		$this->Success();
		return $this;
	}

	public function Expected($expected)
	{
		$this->expected = $expected;
		$this->expected_setted = true;
		return $this;
	}

	public function Result($result)
	{
		$this->result = $result;
		if ($this->expected_setted)
		{
			if ($this->types_compare)
			{
				if ($result===$this->expected) $this->Success();
				else $this->Fail();
			}
			else
			{
				if ($result==$this->expected) $this->Success();
				else $this->Fail();
			}
		}
		return $this;
	}

	public function getExpected()
	{ return $this->expected; }

	public function setName($name)
	{
		$this->name = $name;
		return $this;
	}

	public function getName()
	{ return $this->name; }

	public function getResult()
	{ return $this->result; }

	public function Success()
	{
		$this->type = self::type_success;
		return $this;
	}

	public function Fail()
	{
		$this->type = self::type_fail;
		return $this;
	}

	public function getType()
	{ return $this->type; }

	public function setTypesCompare($types_compare = true)
	{
		if (is_bool($types_compare)) $this->types_compare = $types_compare;
		return $this;
	}

	public function getTypesCompare()
	{ return $this->types_compare; }

	public function addDescription($description)
	{
		if (!empty($this->description)) $this->description .= PHP_EOL.$description;
		else $this->description = $description;
		return $this;
	}

	public function getDescription()
	{ return $this->description; }

}

class MemoryObject_Test
{
	/** @var IMemoryStorage */
	protected $mem;

	public function __construct(IMemoryStorage $mem)
	{
		$this->mem = $mem;
	}

	/**
	 * @return array
	 */
	public function RunTests()
	{
		$results = array();
		$results[] = $this->test_add();
		$results[] = $this->test_save();
		$results[] = $this->test_read();
		$results[] = $this->test_del();
		$results[] = $this->test_del_by_tags();
		$results[] = $this->test_select();
		$results[] = $this->test_select_fx();
		$results[] = $this->test_lock_key();
		$results[] = $this->test_unlock_key();
		$results[] = $this->test_increment();
		$results[] = $this->test_del_old();
		$results[] = $this->test_get_keys();

		return $results;
	}

	public function test_add()
	{
		$results = array();

		$results[] = $result = new TestResult(__METHOD__.' call1 (add)');
		$this->mem->del('t1');
		$call1 = $this->mem->add('t1', 1);
		$result->Expected(true)->Result($call1)->addDescription($this->mem->getLastErr());

		$results[] = $result = new TestResult(__METHOD__.' call2 (replace)');
		$call2 = $this->mem->add('t1', 2);
		$result->Expected(false)->Result($call2)->addDescription($this->mem->getLastErr());

		$results[] = $result = new TestResult(__METHOD__.' call3 (ttl)');
		$this->mem->del('t3');
		$call3 = $this->mem->add('t3', 3, 10);
		$result->Expected(true)->Result($call3)->addDescription($this->mem->getLastErr());

		$results[] = $result = new TestResult(__METHOD__.' call4 (tags string)');
		$this->mem->del('t4');
		$call = $this->mem->add('t4', 1, 10, 'tag');
		$result->Expected(true)->Result($call)->addDescription($this->mem->getLastErr());

		$results[] = $result = new TestResult(__METHOD__.' call4 (tags array)');
		$this->mem->del('t5');
		$call = $this->mem->add('t5', 1, 10, array('tag1', 'tag2'));
		$result->Expected(true)->Result($call)->addDescription($this->mem->getLastErr());

		return $results;
	}

	public function test_del()
	{
		$results = array();

		$results[] = $result = new TestResult(__METHOD__.__LINE__);
		$this->mem->add(__METHOD__.'d1', 1);
		$call = $this->mem->del(__METHOD__.'d1');
		$result->Expected(true)->Result($call)->addDescription($this->mem->getLastErr());
		$check = $this->mem->read(__METHOD__.'d1');
		if (!empty($check)) $result->Fail()->addDescription('variables still in cache');

		$results[] = $result = new TestResult(__METHOD__.__LINE__);
		$this->mem->add(__METHOD__.'d1', 1);
		$this->mem->add(__METHOD__.'d2', 1);
		$call = $this->mem->del(array(__METHOD__.'d1', __METHOD__.'d2'));
		$result->Expected(true)->Result($call)->addDescription($this->mem->getLastErr());
		$check = $this->mem->read(array(__METHOD__.'d1', __METHOD__.'d2'));
		if (!empty($check)) $result->Fail()->addDescription('variables still in cache')->addDescription(print_r($check, 1));

		return $results;
	}

	public function test_del_by_tags()
	{
		$results = array();

		$results[] = $result = new TestResult(__METHOD__.__LINE__);
		$this->mem->add(__METHOD__.'d1', 1, 10, 'tag');
		$call = $this->mem->del_by_tags('tag');
		$result->Expected(true)->Result($call)->addDescription($this->mem->getLastErr());
		$check = $this->mem->read(__METHOD__.'d1');
		if (!empty($check)) $result->Fail()->addDescription('variables still in cache');

		$results[] = $result = new TestResult(__METHOD__.__LINE__);
		$this->mem->add(__METHOD__.'d1', 1, 10, 'tag1');
		$this->mem->add(__METHOD__.'d2', 1, 10, 'tag2');
		$call = $this->mem->del_by_tags(array('tag1', 'tag2'));
		$result->Expected(true)->Result($call)->addDescription($this->mem->getLastErr());
		$check = $this->mem->read(array(__METHOD__.'d1', __METHOD__.'d2'));
		if (!empty($check)) $result->Fail()->addDescription('variables still in cache');

		return $results;
	}

	public function test_del_old()
	{
		$results = array();

		$results[] = $result = new TestResult(__METHOD__.__LINE__);
		$this->mem->save(__METHOD__, 11, 1);
		sleep(2);
		$call = $this->mem->del_old();
		$result->Expected(true)->Result($call)->addDescription($this->mem->getLastErr());
		$check = $this->mem->read(__METHOD__);
		if (!empty($check)) $result->Fail()->addDescription('variable still exists');
		$this->mem->getLastErr();

		return $results;
	}

	public function test_increment()
	{
		$results = array();

		$results[] = $result = new TestResult(__METHOD__.__LINE__);
		$this->mem->save(__METHOD__, 100);
		$call = $this->mem->increment(__METHOD__, 10);
		$check = $this->mem->read(__METHOD__);
		$result->Expected(array(true, 110))->Result(array($call, $check));

		$results[] = $result = new TestResult(__METHOD__.__LINE__);
		$call = $this->mem->increment(__METHOD__, -10);
		$check = $this->mem->read(__METHOD__);
		$result->Expected(array(true, 100))->Result(array($call, $check));

		$results[] = $result = new TestResult(__METHOD__.__LINE__);
		$this->mem->save(__METHOD__, 'string');
		$call = $this->mem->increment(__METHOD__, 10);
		$check = $this->mem->read(__METHOD__);
		$result->Expected(array(true, 'string10'))->Result(array($call, $check));

		return $results;
	}

	public function test_lock_key()
	{
		$results = array();
		$results[] = $result = new TestResult(__METHOD__.__LINE__);

		$this->mem->save(__METHOD__, 1);
		$call = $this->mem->lock_key(__METHOD__, $l);
		$result->Expected(true)->Result($call)->addDescription($this->mem->getLastErr());
		if ($call)
		{
			$check = $this->mem->lock_key(__METHOD__, $l1);
			if ($check) $result->Fail()->addDescription('key was not locked');
			$this->mem->unlock_key($l);
			$this->mem->getLastErr();
		}
		return $results;
	}

	public function test_read()
	{
		$results = array();

		$results[] = $result = new TestResult(__METHOD__.__LINE__);
		$this->mem->save(__METHOD__.'t1', 10);
		$call = $this->mem->read(__METHOD__.'t1');
		$result->Expected(10)->Result($call)->addDescription($this->mem->getLastErr());

		$results[] = $result = new TestResult(__METHOD__.__LINE__);
		$this->mem->save(__METHOD__.'t1', 'string', 10);
		$call = $this->mem->read(__METHOD__.'t1');
		$result->setTypesCompare()->Expected('string')->Result($call)->addDescription($this->mem->getLastErr());

		$results[] = $result = new TestResult(__METHOD__.__LINE__);
		$call = $this->mem->read(__METHOD__.'t1', $ttl_left);
		$result->Expected(array('string', 10))->Result(array($call, $ttl_left))->addDescription($this->mem->getLastErr());

		return $results;

	}

	public function test_save()
	{
		$results = array();

		$results[] = $result = new TestResult(__METHOD__.__LINE__);
		$call = $this->mem->save(__METHOD__.'s1', 100);
		$result->Expected(true)->Result($call)->addDescription($this->mem->getLastErr());
		$check = $this->mem->read(__METHOD__.'s1');
		if ($check!=100) $result->Fail()->addDescription('value mismatch, should be 100, result: '.$check);

		$results[] = $result = new TestResult(__METHOD__.__LINE__);
		$call = $this->mem->save(__METHOD__.'s2', 100, 10);
		$result->Expected(true)->Result($call)->addDescription($this->mem->getLastErr());
		$check = $this->mem->read(__METHOD__.'s2', $ttl_left);
		if ($check!=100) $result->Fail()->addDescription('value mismatch: '.$check);
		if ($ttl_left!=10) $result->Fail()->addDescription('ttl mismatch: '.$ttl_left);

		$results[] = $result = new TestResult(__METHOD__.__LINE__);
		$call = $this->mem->save(__METHOD__.'s21', 100, 0.000001);
		$result->Expected(true)->Result($call)->addDescription($this->mem->getLastErr());
		$check = $this->mem->read(__METHOD__.'s21', $ttl_left);
		if ($check!=100) $result->Fail()->addDescription('value mismatch: '.$check);
		if ($ttl_left < 10) $result->Fail()->addDescription('ttl mismatch: '.$ttl_left);

		$results[] = $result = new TestResult(__METHOD__.__LINE__);
		$call = $this->mem->save(__METHOD__.'s22', 100, 'stringttl');
		$result->Expected(true)->Result($call)->addDescription($this->mem->getLastErr());
		$check = $this->mem->read(__METHOD__.'s22', $ttl_left);
		if ($check!=100) $result->Fail()->addDescription('value mismatch: '.$check);
		if ($ttl_left <= 10) $result->Fail()->addDescription('ttl mismatch: '.$ttl_left);

		$results[] = $result = new TestResult(__METHOD__.__LINE__);
		$call = $this->mem->save(__METHOD__.'s3', 100, 10, 'tag');
		$result->Expected(true)->Result($call)->addDescription($this->mem->getLastErr());
		$check = $this->mem->read(__METHOD__.'s3', $ttl_left);
		if ($check!=100) $result->Fail()->addDescription('value mismatch');
		if ($ttl_left!=10) $result->Fail()->addDescription('ttl mismatch');

		$results[] = $result = new TestResult(__METHOD__.__LINE__);
		$call = $this->mem->save(__METHOD__.'s4', array('z' => 1), 10, array('tag', 'tag1'));
		$result->Expected(true)->Result($call)->addDescription($this->mem->getLastErr());
		$check = $this->mem->read(__METHOD__.'s4', $ttl_left);
		if ($check!==array('z' => 1)) $result->Fail()->addDescription('value mismatch');
		if ($ttl_left!=10) $result->Fail()->addDescription('ttl mismatch');

		return $results;

	}

	public function test_select()
	{
		$results = array();

		$this->mem->save('key1', array('kk1' => 5, 'kk2' => 7));
		$this->mem->save('key2', array('kk1' => 4, 'kk2' => 6));
		$this->mem->save('key3', array('kk1' => 5, 'kk2' => 5));
		$this->mem->save('key4', array('kk1' => 2, 'kk2' => 4));

		$results[] = $result = new TestResult(__METHOD__.__LINE__);
		$call = $this->mem->select(array(array('k' => 'kk1', 'r' => '=', 'v' => 5)));
		$result->Expected(array('kk1' => 5, 'kk2' => 7))->Result($call)->addDescription($this->mem->getLastErr());

		$results[] = $result = new TestResult(__METHOD__.__LINE__);
		$call = $this->mem->select(array(array('k' => 'kk1', 'r' => '>', 'v' => 2),
										array('k' => 'kk2', 'r' => '<', 'v' => 6)));
		$result->Expected(array('kk1' => 5, 'kk2' => 5))->Result($call)->addDescription($this->mem->getLastErr());

		$results[] = $result = new TestResult(__METHOD__.__LINE__);
		$call = $this->mem->select(array(array('k' => 'kk1', 'r' => '=', 'v' => 5)), true);
		$result->Expected(array('key1' => array('kk1' => 5, 'kk2' => 7), 'key3' => array('kk1' => 5, 'kk2' => 5)))->Result($call)->addDescription($this->mem->getLastErr());

		return $results;
	}

	public function test_select_fx()
	{
		$results = array();

		$this->mem->save('key1', array('kk1' => 5, 'kk2' => 7));
		$this->mem->save('key2', array('kk1' => 4, 'kk2' => 6));
		$this->mem->save('key3', array('kk1' => 5, 'kk2' => 5));
		$this->mem->save('key4', array('kk1' => 2, 'kk2' => 4));

		$results[] = $result = new TestResult(__METHOD__.__LINE__);
		$call = $this->mem->select_fx(create_function('$s,$index', "if (\$index=='key1' || \$s['kk2']==7) return true; else return false;"));
		$result->Expected(array('kk1' => 5, 'kk2' => 7))->Result($call)->addDescription($this->mem->getLastErr());

		$results[] = $result = new TestResult(__METHOD__.__LINE__);
		$call = $this->mem->select_fx(create_function('$s,$index', "if (\$s['kk1']==\$s['kk2']) return true; else return false;"));
		$result->Expected(array('kk1' => 5, 'kk2' => 5))->Result($call)->addDescription($this->mem->getLastErr());

		$results[] = $result = new TestResult(__METHOD__.__LINE__);
		$call = $this->mem->select_fx(create_function('$s,$index', "if (\$s['kk1']==\$s['kk2'] || \$index=='key4') return true; else return false;"), true);
		$result->Expected(array('key3' => array('kk1' => 5, 'kk2' => 5), 'key4' => array('kk1' => 2, 'kk2' => 4)))->Result($call)->addDescription($this->mem->getLastErr());

		return $results;
	}

	public function test_unlock_key()
	{
		$results = array();
		$results[] = $result = new TestResult(__METHOD__.__LINE__);

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
		return $results;
	}

	public function test_get_keys()
	{
		$results = array();
		$results[] = $result = new TestResult(__METHOD__.__LINE__);

		$this->mem->save(__METHOD__.':1', 1);
		$this->mem->save(__METHOD__.':2', 1);
		$this->mem->save(__METHOD__.':3', 1);
		$call = $this->mem->get_keys();
		$result->setTypesCompare()->Expected(true)->Result(is_array($call))->addDescription($this->mem->getLastErr());
		$this->mem->del($call);
		$check = $this->mem->get_keys();
		if (!empty($check)) $result->Fail()->addDescription('not all keys was deleted');

		return $results;
	}
}
