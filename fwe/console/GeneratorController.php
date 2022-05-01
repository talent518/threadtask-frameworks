<?php
namespace fwe\console;

use fwe\db\MySQLModel;
use fwe\db\Generator;

class GeneratorController extends Controller {
	public string $genViewPath = '@fwe/views/generators';
	public string $diffCmd = 'colordiff -w';
	
	/**
	 * 列出所有数据库表名
	 * 
	 * @param Generator $generator
	 * @param string $db
	 */
	public function actionTables(Generator $generator, string $db = 'db') {
		$generator->allTable(
			db($db)->pop(),
			function($tables) {
				echo "Table list:\n";
				foreach($tables as $table) {
					echo "$table\n";
				}
			},
			function($data, $e) {
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
	public function actionModel(Generator $generator, string $table, string $class, string $base = MySQLModel::class, string $db = 'db', bool $isComment = false) {
		$generator->oneTable(
			db($db)->pop(),
			$table,
			function($params) use($generator, $table, $class, $base, $isComment) {
				$params['table'] = $table;
				$params['class'] = $class;
				$params['base'] = $base;
				$params['isComment'] = $isComment;
				$target = '@' . str_replace('\\', '/', $class) . '.php';
				
				list($status, $newFile, $oldFile) = $generator->generate($this, "{$this->genViewPath}/model.php", $target, $params);
				
				$n = strlen(ROOT)+1;
				$_newFile = strncmp($newFile, ROOT . '/', $n) ? $newFile : substr($newFile, $n);
				$_oldFile = strncmp($oldFile, ROOT . '/', $n) ? $oldFile : substr($oldFile, $n);
				if($status) {
					if($newFile === $oldFile) {
						$this->formatColor("写入文件成功: ", static::FG_GREEN);
						echo "$_newFile\n";
					} else {
						$this->formatColor("已存在: ", static::FG_RED);
						echo "$_newFile\n";
						do {
							echo "请选择操作方式：\n";
							echo "    Y/y 覆盖: $_newFile\n";
							echo "    D/d 比较差异: $_newFile $_oldFile\n";
							echo "    N/n 删除: $_oldFile\n";
							echo "输入: ";
							$line = trim(fgets(STDIN));
							if(!preg_match('/^[YyDdNn]$/', $line)) continue;
							
							switch(strtolower($line)) {
								case 'y':
									if(rename($oldFile, $newFile)) {
										$this->formatColor("覆盖成功: ", static::FG_GREEN);
										echo "$_newFile\n";
										return;
									} else {
										$this->formatColor("覆盖失败: ", static::FG_RED);
										echo "$_newFile\n";
									}
									break;
								case 'd':
									$return = -1;
									$this->formatColor("{$this->diffCmd} $_newFile $_oldFile\n", static::FG_BLUE);
									passthru("{$this->diffCmd} '$newFile' '$oldFile'", $return);
									if($return === 0) {
										$this->formatColor("无差异\n", static::FG_GREEN);
										goto del;
									}
									break;
								default:
									del:
									if(unlink($oldFile)) {
										$this->formatColor("删除成功: ", static::FG_GREEN);
										echo "$_oldFile\n";
										return;
									} else {
										$this->formatColor("删除失败: ", static::FG_RED);
										echo "$_oldFile\n";
									}
									break;
							}
						} while(true);
					}
				} else {
					$this->formatColor("写入文件失败: ", static::FG_RED);
					echo "$_oldFile\n";
				}
			},
			function($data, $e) {
				throw $e;
			}
		);
	}

	/**
	 * 生成基于MySQL表模型的控制器
	 * 
	 * @param Generator $generator
	 * @param string $model 模型类
	 * @param string $class 控制器类
	 * @param string $base 控制器基类
	 * @param string $search 搜索模型类
	 * @param string $path 视图目录
	 */
	public function actionCtrl(Generator $generator, string $model, string $class, string $base = Controller::class, string $search, string $path) {
	}
}
