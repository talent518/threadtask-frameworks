<?php
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
