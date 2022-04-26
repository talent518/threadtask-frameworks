<?php
namespace fwe\db;

interface IEvent {
	const TYPE_ASSOC = 1;
	const TYPE_NUM = 2;
	const TYPE_OBJ = 3;
	
	const FETCH_ONE = 1;
	const FETCH_COLUMN = 2;
	const FETCH_ALL = 3;
	const FETCH_COLUMN_ALL = 4;
	
	/**
	 * @return string
	 */
	public function getSql();
	
	/**
	 * @return string|int
	 */
	public function getKey();
	
	/**
	 * @return mixed
	 */
	public function getData();

	public function send();

	public function recv();
	
	public function error(\Throwable $e);
}
