<?php
namespace fwe\db;

class MySQLQueryEvent implements MySQLEvent {
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

	public function __construct(MySQLConnection $db, string $sql, int $type = MySQLEvent::TYPE_ASSOC, int $style = MySQLEvent::FETCH_ONE, int $col = 0, $key = null) {
		$this->_db = $db;
		$this->_sql = $sql;
		$this->_type = ($style === MySQLEvent::FETCH_COLUMN || $style === MySQLEvent::FETCH_COLUMN_ALL ? MySQLEvent::TYPE_NUM : $type);
		$this->_style = $style;
		$this->_col = $col;
		$this->_key = $key === null ? $db->eventKey++ : $key;
		if($style === MySQLEvent::FETCH_ALL || MySQLEvent::FETCH_COLUMN_ALL) {
			$this->_data = [];
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
	protected function fetchOne() {
		switch($this->_type) {
			default:
			case MySQLEvent::TYPE_ASSOC:
				$data = $this->_result->fetch_assoc();
				break;
			case MySQLEvent::TYPE_NUM:
				$data = $this->_result->fetch_array(MYSQLI_NUM);
				break;
			case MySQLEvent::TYPE_OBJ:
				$data = $this->_result->fetch_object();
				break;
		}
		return $data;
	}

	public function recv() {
		$this->_result = $this->_db->reapAsyncQuery();
		if($this->_result) {
			switch($this->_style) {
				case MySQLEvent::FETCH_ONE: {
					$this->_data = $this->fetchOne();
					break;
				}
				case MySQLEvent::FETCH_COLUMN: {
					$this->_data = $this->fetchOne()[$this->_col]??null;
					break;
				}
				default:
				case MySQLEvent::FETCH_ALL: {
					$n = $this->_result->num_rows;
					for($i=0; $i<$n; $i++) {
						$this->_data[] = $this->fetchOne();
					}
					break;
				}
				case MySQLEvent::FETCH_COLUMN_ALL: {
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