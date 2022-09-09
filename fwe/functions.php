<?php

/**
 * @param string $id
 * 
 * @return \fwe\db\MySQLPool
 */
function db(string $id = 'db') {
	return \Fwe::$app->get($id);
}

/**
 * @param string $id
 * 
 * @return \fwe\db\RedisPool
 */
function redis(string $id = 'redis') {
	return \Fwe::$app->get($id);
}

/**
 * @return \fwe\curl\Boot
 */
function curl() {
	return \Fwe::$app->get('curl');
}

/**
 * @return \fwe\cache\Cache
 */
function cache(string $id = 'cache') {
	return \Fwe::$app->get($id);
}
