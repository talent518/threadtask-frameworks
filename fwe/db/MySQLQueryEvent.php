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
	protected $_callback;

	public function __construct(MySQLConnection $db, string $sql, int $type = IEvent::TYPE_ASSOC, int $style = IEvent::FETCH_ONE, int $col = 0, $key = null, ?callable $callback = null) {
		$this->_db = $db;
		$this->_sql = $sql;
		$this->_type = ($style === IEvent::FETCH_COLUMN || $style === IEvent::FETCH_COLUMN_ALL ? IEvent::TYPE_NUM : $type);
		$this->_style = $style;
		$this->_col = $col;
		$this->_key = $key === null ? $db->eventKey++ : $key;
		if($style === IEvent::FETCH_ALL || IEvent::FETCH_COLUMN_ALL) {
			$this->_data = [];
		}
		$this->_callback = $callback;
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
	protected function fetchOne() {
		switch($this->_type) {
			default:
			case IEvent::TYPE_ASSOC:
				$data = $this->_result->fetch_assoc();
				break;
			case IEvent::TYPE_NUM:
				$data = $this->_result->fetch_array(MYSQLI_NUM);
				break;
			case IEvent::TYPE_OBJ:
				$data = $this->_result->fetch_object();
				break;
		}
		return $data;
	}

	public function recv() {
		$this->_result = $this->_db->reapAsyncQuery();
		if($this->_result) {
			switch($this->_style) {
				case IEvent::FETCH_ONE: {
					$this->_data = $this->fetchOne();
					break;
				}
				case IEvent::FETCH_COLUMN: {
					$this->_data = $this->fetchOne()[$this->_col]??null;
					break;
				}
				default:
				case IEvent::FETCH_ALL: {
					$n = $this->_result->num_rows;
					for($i=0; $i<$n; $i++) {
						$this->_data[] = $this->fetchOne();
					}
					break;
				}
				case IEvent::FETCH_COLUMN_ALL: {
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
		
		if($this->_callback) $this->_data = call_user_func($this->_callback, $this->_data, $this->_db);
	}

	public function send() {
		if(!$this->_db->query($this->_sql, MYSQLI_ASYNC|MYSQLI_STORE_RESULT)) {
			list($errno, $error) = $this->_db->getError();
			if($errno) throw new Exception("ERROR: {$this->_sql}", compact('errno', 'error'));
		}
		
	}
	
	public function __destruct() {
		if($this->_result) {
			$this->_result->close();
			$this->_result = null;
		}
	}
}