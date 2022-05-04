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
	
	protected function clone(&$params, &$result, &$retkey = null) {
		switch($this->_type) {
			default:
			case static::TYPE_ASSOC:
				$data = [];
				foreach($result as $key=>$val) {
					$data[$key] = $val;
				}
				if($this->_keyBy !== null) $retkey = $data[$this->_keyBy]??null;
				if($this->_valueBy !== null) $data = $data[$this->_valueBy]??null;
				break;
			case static::TYPE_NUM:
				$data = [];
				foreach($params as $val) {
					$data[] = $val;
				}
				if($this->_keyBy !== null) $retkey = $data[$this->_keyBy]??null;
				if($this->_valueBy !== null) $data = $data[$this->_valueBy]??null;
				break;
			case static::TYPE_OBJ:
				$data = new \stdClass();
				foreach($result as $key=>$val) {
					$data->$key = $val;
				}
				if($this->_keyBy !== null) $retkey = $data->{$this->_keyBy}??null;
				if($this->_valueBy !== null) $data = $data->{$this->_valueBy}??null;
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
				case static::FETCH_ONE: {
					if($this->_stmt->fetch()) {
						$this->_data = $this->clone($params, $result);
					} else {
						$this->_data = null;
					}
					break;
				}
				case static::FETCH_COLUMN: {
					if($this->_stmt->fetch()) {
						$this->_data = $params[$this->_col]??null;
					} else {
						$this->_data = null;
					}
					break;
				}
				default:
				case static::FETCH_ALL: {
					$this->_data = [];
					if($this->_keyBy === null) {
						while($this->_stmt->fetch()) {
							$this->_data[] = $this->clone($params, $result);
						}
					} else {
						$key = null;
						while($this->_stmt->fetch()) {
							$data = $this->clone($params, $result, $key);
							$this->_data[$key] = $data;
						}
					}
					break;
				}
				case static::FETCH_COLUMN_ALL: {
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
		
		$t = round(microtime(true) - $this->_time, 6);
		$sql = MySQLQuery::formatSQL($this->_sql, $this->param);
		\Fwe::$app->info("Run time $t seconds, SQL: {$sql}", 'mysql-stmt');
		
		if($this->_success) $this->_data = call_user_func($this->_success, $this->_data, $this->_db);
	}

	public function send() {
		$types = null;
		$this->_db->bindParam($this->param, $types);
		$this->_time = microtime(true);
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
	
	public function error(\Throwable $e) {
		$t = round(microtime(true) - $this->_time, 6);
		$sql = MySQLQuery::formatSQL($this->_sql, $this->param);
		\Fwe::$app->error("Run time $t seconds, SQL: {$sql}, ERROR: $e", 'mysql-stmt');
		
		$this->_data = $e;
		if($this->_error) {
			$this->_data = call_user_func($this->_error, $this->_data, $this->_db);
		} else {
			throw $e;
		}
	}
	
	public function __destruct() {
		if($this->_stmt) $this->_stmt->close();
	}

}
