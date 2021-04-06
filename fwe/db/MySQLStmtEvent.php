<?php
namespace fwe\db;

class MySQLStmtEvent extends MySQLQueryEvent {
	
	/**
	 * @var \mysqli_stmt
	 */
	protected $_stmt;
	
	public $param;
	
	protected function getNumRows() {
		return $this->_stmt->num_rows;
	}
	
	protected function clone(&$params, &$result) {
		switch($this->_type) {
			default:
			case MySQLEvent::TYPE_ASSOC:
				$data = [];
				foreach($result as $key=>$val) {
					$data[$key] = $val;
				}
				break;
			case MySQLEvent::TYPE_NUM:
				$data = [];
				foreach($params as $val) {
					$data[] = $val;
				}
				break;
			case MySQLEvent::TYPE_OBJ:
				$data = new \stdClass();
				foreach($result as $key=>$val) {
					$data->$key = $val;
				}
				break;
		}
		return $data;
	}

	public function recv() {
		if(mysqli_stmt_reap_async_query($this->_stmt) && ($meta = $this->_stmt->result_metadata())) {
			$params = $result = [];
			$n = $meta->field_count;
			for($i=0; $i<$n; $i++) {
				$params[] = &$result[$meta->fetch_field()->name];
			}
			$this->_stmt->bind_result(...$params);
			
			switch($this->_style) {
				case MySQLEvent::FETCH_ONE: {
					if($this->_stmt->fetch()) {
						$this->_data = $this->clone($params, $result);
					} else {
						$this->_data = null;
					}
					break;
				}
				case MySQLEvent::FETCH_COLUMN: {
					if($this->_stmt->fetch()) {
						$this->_data = $params[$this->_col]??null;
					} else {
						$this->_data = null;
					}
					break;
				}
				default:
				case MySQLEvent::FETCH_ALL: {
					$this->_data = [];
					while($this->_stmt->fetch()) {
						$this->_data[] = $this->clone($params, $result);
					}
					break;
				}
				case MySQLEvent::FETCH_COLUMN_ALL: {
					$this->_data = [];
					while($this->_stmt->fetch()) {
						$this->_data[] = $params[$this->_col]??null;
					}
					break;
				}
			}
		} else {
			if($this->_stmt->errno) throw new Exception("ERROR: {$this->_sql}", ['errno'=>$this->_stmt->errno, 'error'=>$this->_stmt->error]);
			$this->_data = [
				'affectedRows' => $this->_stmt->affected_rows,
				'insertId' => $this->_stmt->insert_id,
			];
		}
	}

	public function send() {
		$this->_db->bindParam($this->param, $types);
		
		$this->_stmt = $this->_db->prepare($this->_sql);
		if($this->_stmt) {
			$this->_stmt->bind_param($types, ...$this->param);
			if(mysqli_stmt_async_execute($this->_stmt)) {
				list($errno, $error) = $this->_db->getError();
				if($errno) throw new Exception("ERROR: {$this->_sql}", compact('errno', 'error'));
			}
		} else {
			list($errno, $error) = $this->_db->getError();
			if($errno) throw new Exception("ERROR: {$this->_sql}", compact('errno', 'error'));
		}
	}
	
	public function __destruct() {
		if($this->_stmt) $this->_stmt->close();
	}

}