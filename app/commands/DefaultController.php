<?php
namespace app\commands;

use fwe\console\Controller;
use fwe\db\MySQLConnection;
use fwe\db\MySQLEvent;

class DefaultController extends Controller {

	/**
	 * Hello World
	 */
	public function actionIndex() {
		echo "Hello World\n";
	}
	
	/**
	 * Hello-World
	 */
	public function actionHelloWorld(string $actionID, string $methodName) {
		echo "Hello-World\nactionID: $actionID\nmethodName: $methodName\n";
	}
	
	/**
	 * 异步执行SQL示例
	 */
	public function actionQuery(string $table='clazz') {
		$t = microtime(true);
		db()->pop()->asyncQuery("SHOW TABLES", ['style'=>MySQLEvent::FETCH_COLUMN_ALL])->asyncQuery("SHOW GLOBAL VARIABLES LIKE '%timeout%'", ['type'=>MySQLEvent::TYPE_OBJ, 'style'=>MySQLEvent::FETCH_ALL])->goAsync(function($tables, $variables) use($t) {
			$t = microtime(true) - $t;
			var_dump(get_defined_vars());
		}, function($data) use($t) {
			$t = microtime(true) - $t;
			var_dump(get_defined_vars());
			return false;
		});
			
		$t2 = microtime(true);
		db()->pop()->asyncQuery("SHOW CREATE TABLE `$table`", ['style'=>MySQLEvent::FETCH_COLUMN,'col'=>1])->asyncQuery("SHOW FULL PROCESSLIST", ['style'=>MySQLEvent::FETCH_ALL])->goAsync(function($sql, $list) use($t2) {
			$t2 = microtime(true) - $t2;
			var_dump(get_defined_vars());
		}, function($data) use($t2) {
			$t2 = microtime(true) - $t2;
			var_dump($data);
			return false;
		});
	}
	
	/**
	 * 异步执行预处理SQL示例
	 * 
	 * @param int $id
	 * @param string $table
	 * @param int $newId
	 * @param string $newName
	 * @param string $newDesc
	 */
	public function actionPrepare(int $id = 0, string $table='clazz', int $newId=5, string $newName='Async Prepare', string $newDesc='Test Insert') {
		$t = microtime(true);
		db()->pop()->asyncPrepare("SELECT * FROM clazz WHERE cno>?", [$id], ['style'=>MySQLEvent::FETCH_ALL])->asyncPrepare("REPLACE INTO clazz (cno,cname,cdesc)VALUES(?,?,?)", [$newId,$newName,$newDesc])->goAsync(function($select, $replace) use($t) {
			$this->formatColor('DATA1: ', self::FG_GREEN);

			$t = microtime(true) - $t;
			var_dump(get_defined_vars());
		}, function(MySQLConnection $db, $data, $e) use($t,$newId,$newName,$newDesc) {
			$this->formatColor('ERR1: ', self::FG_RED);

			$t = microtime(true) - $t;
			var_dump(compact('data', 't'));

			if($db->iUsed === null) $db = $db->pool->pop();
			$db->reset()->asyncQuery("SHOW TABLES", ['style'=>MySQLEvent::FETCH_COLUMN_ALL])->asyncPrepare("REPLACE INTO clazz (cno,cname,cdesc)VALUES(?,?,?)", [$newId,$newName,$newDesc])->goAsync(function($tables = null) {
				$this->formatColor('DATA2: ', self::FG_GREEN);
				var_dump($tables);
			}, function($data, $e, $db) {
				$this->formatColor('ERR2: ', self::FG_RED);
				var_dump($data);
			}, 3);
			return $db->iUsed === null;
		}, 3);
	}
	
	/**
	 * 单个异步SQL事件的回调操作。
	 * 
	 * @param int $id
	 * @param string $table
	 */
	public function actionCallback(int $id = 1, string $table='%us%') {
		$t = microtime(true);
		db()->pop()->asyncQuery("SHOW TABLES LIKE 'clazz'", ['callback'=>function($data, $db) use($id,$table) {
			$this->formatColor('CALL1: ', self::FG_BLUE);
			var_dump($data);
			if($data) {
				$db->asyncPrepare("SELECT * FROM clazz WHERE cno=?", [$id], ['style'=>MySQLEvent::FETCH_ONE]);
				return "OK: $data";
			} else {
				return "ERR: $table";
			}
		}, 'style'=>MySQLEvent::FETCH_COLUMN])->asyncPrepare("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=database() AND TABLE_NAME LIKE ?", [$table], ['callback'=>function($data, $db) use($table) {
			$this->formatColor('CALL2: ', self::FG_BLUE);
			var_dump($data);
			if($data) {
				$db->asyncQuery("SELECT * FROM `$data` LIMIT 1", ['style'=>MySQLEvent::FETCH_ONE, 'type'=>MySQLEvent::TYPE_OBJ]);
				return "OK: $data";
			} else {
				return "ERR: $table";
			}
		}, 'style'=>MySQLEvent::FETCH_COLUMN])->goAsync(function($data) use($t) {
			$this->formatColor('DATA: ', self::FG_GREEN);
			var_dump($data, microtime(true)-$t);
		}, function($data) use($t) {
			$this->formatColor('ERR: ', self::FG_RED);
			var_dump($data, microtime(true)-$t);
		});
	}
}
