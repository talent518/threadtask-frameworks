<?php
namespace fwe\db;

class MySQLStmtEvent extends MySQLQueryEvent {
	
	/**
	 * @var \mysqli_stmt
	 */
	protected $_stmt;

	public function recv() {
	}

	public function send() {
		$this->_stmt = $this->_db->prepare($this->_sql);
		$this->_stmt->bind_param($this->_types, ...$this->_params);
		mysqli_stmt_async_execute($this->_stmt);
	}
	
	public function __destruct() {
		parent::__destruct();
		
		if($this->_stmt) $this->_stmt->close();
	}

}