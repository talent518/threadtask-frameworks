<?php
namespace fwe\fibers;

use fwe\db\RedisConnection;

/**
 *
 * @see RedisConnection
 *
 * @author abao
 */
class RedisFiber {

	/**
	 *
	 * @var RedisConnection
	 */
	protected $db;

	public function __construct(RedisConnection $db) {
		$this->db = $db;
	}

	public function __destruct() {
		$this->db->push();
	}

	/**
	 *
	 * @param RedisConnection $db
	 * @return RedisConnection
	 */
	public static function use(RedisConnection $db) {
		return \Fwe::createObject(get_called_class(), compact('db'));
	}

	/**
	 *
	 * @param string $id
	 * @return RedisConnection
	 */
	public static function pop(string $id = 'redis') {
		$db = redis($id)->pop();
		return static::use($db);
	}

	protected $idx = 0;

	protected function go() {
		if(! $this->db->isUsing()) {
			echo "goAsync\n";
			$cb = function () {
			};
			$this->db->goAsync($cb, $cb);
		}

		return \Fiber::suspend($this->idx ++);
	}

	public function __call(string $name, array $params) {
		if(method_exists($this->db, $name)) {
			return $this->db->$name(...$params);
		}

		$fiber = \Fiber::getCurrent();
		$cmd = implode(' ', [
			$name,
			...$params
		]);

		$idx = $this->idx;

		$this->db->beginAsync();
		$this->db->setAsyncCallback(function ($result) use ($fiber, $cmd, $idx) {
			echo "[ OK][$idx] $cmd\n";
			$fiber->resume($result);
		}, function ($e) use ($fiber, $cmd, $idx) {
			echo "[ERR][$idx] $cmd: $e\n";
			$fiber->throw($e);
		});
		$this->db->__call($name, $params);

		return $this->go();
	}
}

