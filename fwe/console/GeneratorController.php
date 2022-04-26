<?php
namespace fwe\console;

use fwe\db\IEvent;
use fwe\db\MySQLModel;

class GeneratorController extends Controller {
	/**
	 * 生成MySQL的表模型
	 *
	 * @param string $table 表名
	 * @param string $class 表模型的类名(包括命名空间的命名)
	 * @param string $base $class类的父类类名(包括命名空间的命名)
	 * @param string $db MySQL数据库的连接ID
	 * @param bool $isComment 是否使用相应数据表列的注释生成属性标签
	 */
	public function actionModel(string $table, string $class, string $base = MySQLModel::class, string $db = 'db', bool $isComment = false) {
		db($db)->pop()
		->asyncQuery("SHOW FULL FIELDS FROM `$table`", ['key'=>'fields', 'style'=>IEvent::FETCH_ALL])
		->asyncQuery("SHOW INDEX FROM `$table`", ['key'=>'indexes', 'style'=>IEvent::FETCH_ALL])
		->goAsync(function($fields, $indexes) {
			$json = json_encode(get_defined_vars(), JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
			echo "SUCCESS: {$json}\n";
		}, function(array $data) {
			$json = json_encode($data, JSON_PRETTY_PRINT);
			echo "ERROR: {$json}\n";
		});
	}
	
	/**
	 * 生成基于MySQL表模型的控制器
	 */
	public function actionCtrl(string $model, string $class, string $base = Controller::class) {
	}
}
