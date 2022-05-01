<?php
namespace fwe\web;

use fwe\base\Controller;
use fwe\db\Generator;
use fwe\db\MySQLModel;

class GeneratorController extends Controller {
	public string $genViewPath = '@fwe/views/generators';
	
	/**
	 * 列出所有数据库表名
	 *
	 * @param Generator $generator
	 * @param string $db
	 */
	public function actionTables(Generator $generator, RequestEvent $request, string $db = 'db') {
		$db = db($db)->pop();
		$generator->allTable(
			$db,
			function($tables) use($db, $request) {
				$request->getResponse()->json($tables);
				$db->push();
			},
			function($data, $e) use($db) {
				$db->push();
				throw $e;
			}
		);
	}
	
	/**
	 * 生成MySQL的表模型
	 *
	 * @param Generator $generator
	 * @param string $table 表名
	 * @param string $class 表模型的类名(包括命名空间的命名)
	 * @param string $base $class类的父类类名(包括命名空间的命名)
	 * @param string $db MySQL数据库的连接ID
	 * @param bool $isComment 是否使用相应数据表列的注释生成属性标签
	 */
	public function actionModel(RequestEvent $request, Generator $generator, string $table, string $class, string $base = MySQLModel::class, string $db = 'db', bool $isComment = false, bool $isOver = false) {
		$generator->oneTable(
			db($db)->pop(),
			$table,
			function($params) use($generator, $table, $class, $base, $isComment, $request, $isOver) {
				$params['table'] = $table;
				$params['class'] = $class;
				$params['base'] = $base;
				$params['isComment'] = $isComment;
				$target = '@' . str_replace('\\', '/', $class) . '.php';
				
				list($status, $newFile, $oldFile) = $generator->generate($this, "{$this->genViewPath}/model.php", $target, $params, $isOver);
				
				$n = strlen(ROOT)+1;
				if($status) {
					$_newFile = strncmp($newFile, ROOT . '/', $n) ? $newFile : substr($newFile, $n);
					$request->getResponse()->json([
						'status' => true,
						'message' => $newFile === $oldFile ? "写入文件成功: $_newFile" : "文件已存在: $_newFile",
						'source' => $newFile === $oldFile ? null : file_get_contents($newFile),
						'target' => $newFile === $oldFile ? highlight_file($oldFile, true) : file_get_contents($oldFile),
					]);
				} else {
					$_oldFile = strncmp($oldFile, ROOT . '/', $n) ? $oldFile : substr($oldFile, $n);
					$request->getResponse()->json([
						'status' => false,
						'message' => "写入文件失败: $_oldFile",
					]);
				}
			},
			function($data, $e) use($request) {
				$request->getResponse()->setStatus(500)->json([
					'status' => false,
					'message' => (string) $e,
				]);
			}
		);
	}
}
