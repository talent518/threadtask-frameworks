<?php
namespace fwe\db;

class MySQLQueryEvent implements IEvent {
	/**
	 *
	 * @var MySQLConnection
	 */
	protected $_db;
	
	/**
	 *
	 * @var string
	 */
	protected $_sql;

	/**
	 * @var string|int
	 */
	protected $_key;
	
	/**
	 * @var \mysqli_result
	 */
	protected $_result;
	
	/**
	 * @var int
	 */
	protected $_type, $_style, $_col;
	
	/**
	 * @var mixed
	 */
	protected $_data;
	
	/**
	 * @var callable
	 */
	protected $_success, $_error;
	
	/**
	 * @var mixed
	 */
	protected $_keyBy, $_valueBy;
	
	protected $_time;

	public function __construct(MySQLConnection $db, string $sql, int $type = self::TYPE_ASSOC, int $style = self::FETCH_ONE, int $col = 0, $key = null, ?callable $success = null, ?callable $error = null, $keyBy = null, $valueBy = null) {
		$this->_db = $db;
		$this->_sql = $sql;
		$this->_type = ($style === static::FETCH_COLUMN || $style === static::FETCH_COLUMN_ALL ? static::TYPE_NUM : $type);
		$this->_style = $style;
		$this->_col = $col;
		$this->_key = $key === null ? $db->eventKey++ : $key;
		if($style === static::FETCH_ALL || static::FETCH_COLUMN_ALL) {
			$this->_data = [];
		}
		$this->_success = $success;
		$this->_error = $error;
		if($style === static::FETCH_ALL) {
			$this->_keyBy = $keyBy;
			$this->_valueBy = $valueBy;
		}
	}
	
	public function getSql() {
		return $this->_sql;
	}

	public function getData() {
		return $this->_data;
	}

	public function getKey() {
		return $this->_key;
	}

	/**
	 * @return mixed
	 */
	protected function fetchOne(&$key = null) {
		switch($this->_type) {
			default:
			case static::TYPE_ASSOC:
				$data = $this->_result->fetch_assoc();
				if($this->_keyBy !== null) $key = $data[$this->_keyBy]??null;
				if($this->_valueBy !== null) $data = $data[$this->_valueBy]??null;
				break;
			case static::TYPE_NUM:
				$data = $this->_result->fetch_array(MYSQLI_NUM);
				if($this->_keyBy !== null) $key = $data[$this->_keyBy]??null;
				if($this->_valueBy !== null) $data = $data[$this->_valueBy]??null;
				break;
			case static::TYPE_OBJ:
				$data = $this->_result->fetch_object();
				if($this->_keyBy !== null) $key = $data->{$this->_keyBy}??null;
				if($this->_valueBy !== null) $data = $data->{$this->_valueBy}??null;
				break;
		}
		return $data;
	}

	public function recv() {
		$this->_result = $this->_db->reapAsyncQuery();
		if($this->_result instanceof \mysqli_result) {
			switch($this->_style) {
				case static::FETCH_ONE: {
					$this->_data = $this->fetchOne();
					break;
				}
				case static::FETCH_COLUMN: {
					$this->_data = $this->fetchOne()[$this->_col]??null;
					break;
				}
				default:
				case static::FETCH_ALL: {
					$n = $this->_result->num_rows;
					if($this->_keyBy === null) {
						for($i=0; $i<$n; $i++) {
							$this->_data[] = $this->fetchOne();
						}
					} else {
						$key = null;
						for($i=0; $i<$n; $i++) {
							$data = $this->fetchOne($key);
							$this->_data[$key] = $data;
						}
					}
					break;
				}
				case static::FETCH_COLUMN_ALL: {
					$n = $this->_result->num_rows;
					for($i=0; $i<$n; $i++) {
						$this->_data[] = $this->fetchOne()[$this->_col]??null;
					}
					break;
				}
			}
		} else {
			list($errno, $error) = $this->_db->getError();
			if($errno) throw new Exception("ERROR: {$this->_sql}", compact('errno', 'error'));
			
			$this->_data = [
				'affectedRows' => $this->_db->getAffectedRows(),
				'insertId' => $this->_db->getInsertId(),
			];
		}
		
		$t = round(microtime(true) - $this->_time, 6);
		\Fwe::$app->info("Run time $t seconds, SQL: {$this->_sql}", 'mysql-query');
		
		if($this->_success) $this->_data = call_user_func($this->_success, $this->_data, $this->_db);
	}

	public function send() {
		$this->_time = microtime(true);
		if(!$this->_db->query($this->_sql, MYSQLI_ASYNC|MYSQLI_STORE_RESULT)) {
			list($errno, $error) = $this->_db->getError();
			if($errno) throw new Exception("ERROR: {$this->_sql}", compact('errno', 'error'));
		}
	}
	
	public function error(\Throwable $e) {
		$t = round(microtime(true) - $this->_time, 6);
		\Fwe::$app->error("Run time $t seconds, SQL: {$this->_sql}, ERROR: $e", 'mysql-query');

		$this->_data = $e;
		if($this->_error) {
			$this->_data = call_user_func($this->_error, $this->_data, $this->_db);
		} else {
			throw $e;
		}
	}
	
	public function __destruct() {
		if($this->_result instanceof \mysqli_result) {
			$this->_result->close();
			$this->_result = null;
		}
	}
}
