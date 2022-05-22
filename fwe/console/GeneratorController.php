<?php
namespace fwe\console;

use fwe\base\Controller;
use fwe\base\Exception;
use fwe\db\Generator;
use fwe\db\MySQLModel;
use fwe\web\Controller as RestfulController;

class GeneratorController extends \fwe\console\Controller {
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
	 * @param bool $isOver 是否覆盖
	 */
	public function actionModel(Generator $generator, string $table, string $class, string $base = MySQLModel::class, string $db = 'db', bool $isComment = false, bool $isOver = false) {
		if($base !== MySQLModel::class && !is_subclass_of($base, MySQLModel::class)) {
			$class = MySQLModel::class;
			throw new Exception("$base 不是 $class 的子类");
		}
		$generator->oneTable(
			db($db)->pop(),
			$table,
			function($params) use($generator, $table, $class, $base, $isComment, $isOver) {
				$params['table'] = $table;
				$params['class'] = $class;
				$params['base'] = $base;
				$params['isComment'] = $isComment;
				$target = '@' . str_replace('\\', '/', $class) . '.php';
				
				list($status, $newFile, $oldFile) = $generator->generate($this, "{$this->genViewPath}/model.php", $target, $params, $isOver);
				$this->generator($status, $newFile, $oldFile);
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
	 * @param string $search 搜索模型类
	 * @param string $path 视图目录
	 * @param string $base 控制器基类
	 * @param bool $isTpl 是否使用tpl模板引擎
	 * @param bool $isOver 是否覆盖
	 */
	public function actionCtrl(Generator $generator, string $model, string $class, string $search, ?string $path = null, string $base = Controller::class, bool $isTpl = false, bool $isOver = false) {
		if($model !== MySQLModel::class && !is_subclass_of($model, MySQLModel::class)) {
			$class = MySQLModel::class;
			throw new Exception("$model 不是 $class 的子类");
		}
		if($base !== Controller::class && !is_subclass_of($base, Controller::class)) {
			$class = Controller::class;
			throw new Exception("$base 不是 $class 的子类");
		}
		
		$params = compact('model', 'class', 'base', 'search');
		$params['isRestful'] = ($base === RestfulController::class || is_subclass_of($base, RestfulController::class));
		$params['isJson'] = ($path === null || $path === '' || $params['isRestful']);
		
		$classes = preg_split('/[^a-zA-Z0-9]+/', $search, -1, PREG_SPLIT_NO_EMPTY);
		$params['className'] = array_pop($classes);
		$params['namespace'] = implode('\\', $classes);
		
		$searchFile = '@' . str_replace('\\', '/', $search) . '.php';
		list($status, $newFile, $oldFile) = $generator->generate($this, "{$this->genViewPath}/ctrl-search.php", $searchFile, $params, $isOver);
		$this->generator($status, $newFile, $oldFile);
		
		$classes = preg_split('/[^a-zA-Z0-9]+/', $class, -1, PREG_SPLIT_NO_EMPTY);
		$params['className'] = array_pop($classes);
		$params['namespace'] = implode('\\', $classes);
		$params['isTpl'] = $isTpl;
		
		$ctrlFile = '@' . str_replace('\\', '/', $class) . '.php';
		list($status, $newFile, $oldFile) = $generator->generate($this, "{$this->genViewPath}/ctrl-class.php", $ctrlFile, $params, $isOver);
		$this->generator($status, $newFile, $oldFile);
		
		if(!$params['isJson']) {
			$ext = ($isTpl ? 'tpl' : 'php');
			$suffix = ($isTpl ? '-tpl' : null);
			
			list($status, $newFile, $oldFile) = $generator->generate($this, "{$this->genViewPath}/ctrl-index{$suffix}.php", "$path/index.$ext", $params, $isOver);
			$this->generator($status, $newFile, $oldFile);
			list($status, $newFile, $oldFile) = $generator->generate($this, "{$this->genViewPath}/ctrl-form{$suffix}.php", "$path/form.$ext", $params, $isOver);
			$this->generator($status, $newFile, $oldFile);
			list($status, $newFile, $oldFile) = $generator->generate($this, "{$this->genViewPath}/ctrl-create{$suffix}.php", "$path/create.$ext", $params, $isOver);
			$this->generator($status, $newFile, $oldFile);
			list($status, $newFile, $oldFile) = $generator->generate($this, "{$this->genViewPath}/ctrl-update{$suffix}.php", "$path/update.$ext", $params, $isOver);
			$this->generator($status, $newFile, $oldFile);
			list($status, $newFile, $oldFile) = $generator->generate($this, "{$this->genViewPath}/ctrl-view{$suffix}.php", "$path/view.$ext", $params, $isOver);
			$this->generator($status, $newFile, $oldFile);
		}
	}
	
	protected function generator(bool $status, string $newFile, string $oldFile) {
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
				$isFirst = true;
				do {
					if($isFirst) {
						$isFirst = false;
						$line = 'd';
					} else {
						echo "请选择操作方式：\n";
						echo "    Y/y 覆盖: $_newFile\n";
						echo "    D/d 比较差异: $_newFile $_oldFile\n";
						echo "    N/n 删除: $_oldFile\n";
						echo "输入: ";
						$line = trim(fgets(STDIN));
						if(!preg_match('/^[YyDdNn]$/', $line)) continue;
					}
					
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
								// $this->formatColor("无差异: ", static::FG_GREEN);
								// echo "$_newFile $_oldFile\n";
								if(unlink($oldFile)) {
									return;
								} else {
									$this->formatColor("删除失败: ", static::FG_RED);
									echo "$_oldFile\n";
								}
							}
							break;
						default:
							if(unlink($oldFile)) {
								$this->formatColor("删除成功: ", static::FG_GREEN);
								echo "$_oldFile\n";
							} else {
								$this->formatColor("删除失败: ", static::FG_RED);
								echo "$_oldFile\n";
							}
							return;
					}
				} while(true);
			}
		} else {
			$this->formatColor("写入文件失败: ", static::FG_RED);
			echo "$_oldFile\n";
		}
	}
}
