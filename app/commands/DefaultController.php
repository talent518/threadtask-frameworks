<?php
namespace app\commands;

use fwe\base\Controller;
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
		$this->db()->pop()->asyncQuery("SHOW TABLES", ['style'=>MySQLEvent::FETCH_COLUMN_ALL])->asyncQuery("SHOW GLOBAL VARIABLES LIKE '%timeout%'", ['type'=>MySQLEvent::TYPE_OBJ, 'style'=>MySQLEvent::FETCH_ALL])->goAsync(function($tables, $variables) use($t) {
			$t = microtime(true) - $t;
			var_dump(get_defined_vars());
		}, function($data) use($t) {
			$t = microtime(true) - $t;
			var_dump(get_defined_vars());
			return false;
		});
			
		$t2 = microtime(true);
		$this->db()->pop()->asyncQuery("SHOW CREATE TABLE `$table`", ['style'=>MySQLEvent::FETCH_COLUMN,'col'=>1])->asyncQuery("SHOW FULL PROCESSLIST", ['style'=>MySQLEvent::FETCH_ALL])->goAsync(function($sql, $list) use($t2) {
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
		$this->db()->pop()->asyncPrepare("SELECT * FROM clazz WHERE cno>?", [$id], ['style'=>MySQLEvent::FETCH_ALL])->asyncPrepare("REPLACE INTO clazz (cno,cname,cdesc)VALUES(?,?,?)", [$newId,$newName,$newDesc])->goAsync(function($select, $replace) use($t) {
			$t = microtime(true) - $t;
			var_dump(get_defined_vars());
		}, function($data) use($t) {
			$t = microtime(true) - $t;
			var_dump(get_defined_vars());
			return false;
		});
	}
}
