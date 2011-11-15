<?php
namespace Jamm\Memory\Shm;
use Jamm\Memory\IKeyLocker;

class MultiAccess implements IMutex
{
	protected $read_q_key = 1;
	protected $write_q_key = 2;
	protected $writers_mutex_key = 3;
	protected $err_log = array();
	protected $read_q;
	protected $write_q;
	protected $writers_mutex;
	protected $writers_count = 0;
	protected $readers_count = 0;
	protected $mutex_acquired = false;
	protected $max_wait_time = 0.05;

	const readers = 1;
	const writers = 2;

	public function get_access_read(&$auto_unlocker_reference)
	{
		if (($this->writers_count <= 0) && ($this->readers_count <= 0)) //if it is nested call - access should be given only once
		{
			if (!$this->wait(self::writers)) return false; //if somebody are writing here now - we will wait

			$sent = $this->increment(self::readers); //increment count of readers - writers will be waiting for us while we read.
			if (!$sent) return false;
		}

		$auto_unlocker_reference = new \Jamm\Memory\KeyAutoUnlocker(array($this, 'release_access_read'));
		$this->readers_count++;
		return true;
	}

	public function release_access_read(IKeyLocker $autoUnlocker = NULL)
	{
		if (!empty($autoUnlocker)) $autoUnlocker->revoke();
		if ($this->readers_count > 0) $this->readers_count--;
		if ($this->readers_count <= 0) $this->decrement(self::readers); //tell to writers, that we have read and they can write now
		return true;
	}

	public function get_access_write(&$auto_unlocker_reference)
	{
		if ($this->writers_count <= 0) //if we are writers already - don't need to lock semaphore again
		{
			if ($this->readers_count > 0) //if we got reader's access and want to write - release our reader's access and got new access - writer's (else process will wait itself for a while)
			{
				$this->readers_count = 0;
				$this->release_access_read($auto_unlocker_reference);
			}

			//acquire mutex for writing
			if (!$this->acquire_writers_mutex()) return false;

			//only 1 writer can send message to writers queue, so if in queue more than 1 message - it's somebody's error, and we will fix it now:
			$this->clean_queue(self::writers);

			//tell to readers, that they should wait while we will write
			//this action should be made before writer will wait for readers
			$sent = $this->increment(self::writers); //after this command, readers will wait, until we will leave the queue
			if (!$sent)
			{
				$this->release_writers_mutex();
				return false;
			}

			//but if readers has come before us - wait, until they finish
			if (!$this->wait(self::readers))
			{
				$this->decrement(self::writers);
				$this->release_writers_mutex();
				return false;
			}
		}

		$auto_unlocker_reference = new \Jamm\Memory\KeyAutoUnlocker(array($this, 'release_access_write'));
		$this->writers_count++;
		//and now we can write :)
		return true;
	}

	public function release_access_write(IKeyLocker $autoUnlocker = NULL)
	{
		if (!empty($autoUnlocker)) $autoUnlocker->revoke();
		if ($this->writers_count > 0) $this->writers_count--;
		if ($this->writers_count <= 0)
		{
			$this->decrement(self::writers); //tell to readers, that they can read now
			$this->release_writers_mutex();
		}
		return true;
	}

	/**
	 * Returned value of this function should not be ignored.
	 * 'False' means that access should not be granted.
	 * @return bool
	 */
	protected function acquire_writers_mutex()
	{
		if (!$this->mutex_acquired)
		{
			if (empty($this->writers_mutex)) $this->writers_mutex = sem_get($this->writers_mutex_key, 1, 0777, 1);
			if (empty($this->writers_mutex)) return false;
			ignore_user_abort(true); //don't hang with semaphore, please :)
			set_time_limit(30);
			if (!sem_acquire($this->writers_mutex))
			{
				$this->err_log[] = 'Can not acquire writers mutex';
				return false;
			}
			$this->mutex_acquired = true;
		}
		return true;
	}

	protected function release_writers_mutex()
	{
		if (!empty($this->writers_mutex))
		{
			if (sem_release($this->writers_mutex))
			{
				unset($this->writers_mutex);
				$this->mutex_acquired = false;
				return true;
			}
			else return false;
		}
		return true;
	}

	protected function clean_queue($type = self::writers)
	{
		$q = $this->select_q($type);
		$stat = msg_stat_queue($q);
		if ($stat['msg_qnum'] > 0)
		{
			for ($i = $stat['msg_qnum']; $i > 0; $i--)
			{
				msg_receive($q, $type, $t, 100, $msg, false, MSG_IPC_NOWAIT+MSG_NOERROR, $err);
			}
		}
	}

	protected function increment($type = self::readers)
	{
		$q = $this->select_q($type);
		if (empty($q)) return false;
		$sent = msg_send($q, $type, $type, false, false, $err);
		if ($sent==false)
		{
			$counter = $this->get_counter($type);
			$this->err_log[] = 'Message was not sent to queue '.($type==self::readers ? 'readers '.$this->read_q_key
					: 'writers '.$this->write_q_key)
					.' counter: '.$counter.', error: '.$err;
			return false;
		}
		return true;
	}

	protected function decrement($type = self::readers)
	{
		$q = $this->select_q($type);
		if (empty($q)) return false;
		$recieve = msg_receive($q, $type, $t, 100, $msg, false, MSG_IPC_NOWAIT+MSG_NOERROR, $err);
		if ($recieve===false)
		{
			$counter = $this->get_counter($type);
			if ($counter > 0)
			{
				$this->err_log[] = 'Message was not recieved from queue '.($type==self::readers
						? 'readers '.$this->read_q_key : 'writers '.$this->write_q_key)
						.' counter: '.$counter.', error: '.$err;
				return false;
			}
		}
		return true;
	}

	public function get_counter($type = self::writers)
	{
		$q = $this->select_q($type);
		$stat = msg_stat_queue($q);
		return $stat['msg_qnum'];
	}

	/**
	 * Wait for queue. If this function has returned 'false', access should not be granted.
	 * @param int $type
	 * @return bool
	 */
	protected function wait($type = self::writers)
	{
		$q = $this->select_q($type);
		if (empty($q)) return false;

		$stat = msg_stat_queue($q);
		if ($stat['msg_qnum'] > 0)
		{
			$starttime = microtime(true);
			do
			{
				$stat = msg_stat_queue($q);
				if (empty($stat)) break;
				if ($stat['msg_qnum'] <= 0) break;

				if ((microtime(true)-$starttime) > $this->max_wait_time) return false;
			}
			while ($stat['msg_qnum'] > 0);
		}
		return true;
	}

	protected function select_q($type)
	{
		if ($type==self::readers)
		{
			if (empty($this->read_q)) $this->read_q = msg_get_queue($this->read_q_key, 0777);
			$q = $this->read_q;
		}
		else
		{
			if (empty($this->write_q)) $this->write_q = msg_get_queue($this->write_q_key, 0777);
			$q = $this->write_q;
		}
		return $q;
	}

	public function __construct($id = '')
	{
		if (!empty($id))
		{
			if (is_string($id))
			{
				if (is_file($id) || is_dir($id)) $id = ftok($id, 'I');
				else  $id = intval($id);
			}
			$this->read_q_key += $id*10;
			$this->write_q_key += $id*10;
			$this->writers_mutex_key += $id*10;
		}
	}

	public function __destruct()
	{
		if ($this->writers_count > 0)
		{
			$this->err_log[] = 'writers count = '.$this->writers_count;
			$this->writers_count = 0;
			$this->release_access_write();
		}
		$this->release_writers_mutex();

		if ($this->readers_count > 0)
		{
			$this->err_log[] = 'readers count = '.$this->readers_count;
			$this->readers_count = 0;
			$this->release_access_read();
		}

		if (!empty($this->err_log))
		{
			trigger_error('MultiAccess errors '.implode("\n", $this->err_log), E_USER_NOTICE);
		}
	}

	public function getReadQKey()
	{ return $this->read_q_key; }

	public function getWriteQKey()
	{ return $this->write_q_key; }

	public function getErrLog()
	{ return $this->err_log; }

	public function setMaxWaitTime($max_wait_time)
	{ $this->max_wait_time = floatval($max_wait_time); }

}
