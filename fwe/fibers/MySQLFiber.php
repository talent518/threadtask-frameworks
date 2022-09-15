<?php
namespace fwe\fibers;

use fwe\db\MySQLConnection;

class MySQLFiber {

	/**
	 *
	 * @var MySQLConnection
	 */
	protected $db;

	public function __construct(MySQLConnection $db) {
		$this->db = $db;
	}

	/**
	 *
	 * @param MySQLConnection $db
	 * @return MySQLConnection
	 */
	public static function use(MySQLConnection $db) {
		return \Fwe::createObject(get_called_class(), compact('db'));
	}

	/**
	 *
	 * @param bool $isSlave
	 * @param string $id
	 * @return MySQLConnection
	 */
	public static function pop(bool $isSlave = true, string $id = 'db') {
		$db = db($id)->pop($isSlave);

		return static::use($db);
	}

	protected function go() {
		if(! $this->db->isUsing()) {
			$cb = function () {
			};
			$this->db->goAsync($cb, $cb);
		}

		return \Fiber::suspend();
	}

	public function beginTransaction(int $flags = 0) {
		$suffix = null;
		switch($flags) {
			case MYSQLI_TRANS_START_READ_ONLY:
				$suffix .= ' READ ONLY';
				break;
			case MYSQLI_TRANS_START_READ_WRITE:
				$suffix .= ' READ WRITE';
				break;
			case MYSQLI_TRANS_START_WITH_CONSISTENT_SNAPSHOT:
				$suffix .= ' WITH CONSISTENT SNAPSHOT';
				break;
			default:
				break;
		}
		
		return $this->query("START TRANSACTION{$suffix}");
	}
	
	public function setTransaction(int $level = 0, int $mode = 0) {
		$suffix = null;
		switch($level) {
			case MySQLConnection::LEVEL_REPEATABLE_READ:
				$suffix .= ' ISOLATION LEVEL REPEATABLE READ';
				break;
			case MySQLConnection::LEVEL_READ_COMMITTED:
				$suffix .= ' ISOLATION LEVEL READ COMMITTED';
				break;
			case MySQLConnection::LEVEL_READ_UNCOMMITTED:
				$suffix .= ' ISOLATION LEVEL READ UNCOMMITTED';
				break;
			case MySQLConnection::LEVEL_SERIALIZABLE:
				$suffix .= ' ISOLATION LEVEL SERIALIZABLE';
				break;
			default:
				return;
		}
		
		switch($mode) {
			case MySQLConnection::MODE_READ_WRITE:
				$suffix .= ' READ WRITE';
				break;
			case MySQLConnection::MODE_READ_ONLY:
				$suffix .= ' READ ONLY';
				break;
			default:
				break;
		}
		
		return $this->query("SET TRANSACTION{$suffix}");
	}

	public function commit() {
		$this->query('COMMIT');
		
		return true;
	}

	public function rollback() {
		$this->query('ROLLBACK');
		
		return true;
	}

	public function query(string $sql, array $options = []) {
		$fiber = \Fiber::getCurrent();

		$this->db->asyncQuery($sql, [
			'success' => function ($result) use ($fiber) {
				$fiber->resume($result);
			},
			'error' => function ($e) use ($fiber) {
				$fiber->throw($e);
			},
		] + $options);

		return $this->go();
	}

	public function prepare(string $sql, array $params = [], array $options = []) {
		$fiber = \Fiber::getCurrent();

		$this->db->asyncPrepare($sql, $params, [
			'success' => function ($result) use ($fiber) {
				$fiber->resume($result);
			},
			'error' => function ($e) use ($fiber) {
				$fiber->throw($e);
			},
		] + $options);

		return $this->go();
	}
}

