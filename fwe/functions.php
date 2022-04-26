<?php

/**
 * 
 * 
 * @param array $array
 * @return boolean
 */
function is_assoc(array $array) {
	foreach(array_keys($array) as $k => $v) {
		if($k !== $v) {
			return true;
		}
	}
	return false;
}

/**
 * @param string $id
 * @return \fwe\db\MySQLPool
 */
function db(string $id = 'db') {
	return \Fwe::$app->get($id);
}

/**
 * @param string $id
 * @return \fwe\db\RedisPool
 */
function redis(string $id = 'redis') {
	return \Fwe::$app->get($id);
}

/**
 * @param string $id
 * @return \fwe\curl\Boot
 */
function curl() {
	return \Fwe::$app->get('curl');
}