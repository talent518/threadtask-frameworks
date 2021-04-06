<?php
namespace fwe\db;

interface MySQLEvent {
	const TYPE_ASSOC = 1;
	const TYPE_NUM = 2;
	const TYPE_OBJ = 3;
	
	const FETCH_ONE = 1;
	const FETCH_COLUMN = 2;
	const FETCH_ALL = 3;
	const FETCH_COLUMN_ALL = 4;
	
	/**
	 * @return string|int
	 */
	public function getKey();

	public function send();

	public function recv();

	/**
	 * @return mixed
	 */
	public function getData();
}