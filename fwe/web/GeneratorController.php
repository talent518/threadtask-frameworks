<?php
namespace fwe\web;

use fwe\base\Controller;
use fwe\base\Exception;
use fwe\db\Generator;
use fwe\db\MySQLModel;
use fwe\web\Controller as RestfulController;

class GeneratorController extends Controller {
	public string $genViewPath = '@fwe/views/generators';
	
	/**
	 * 列出所有数据库表名
	 *
	 * @param RequestEvent $request
	 * @param Generator $generator
	 * @param string $db
	 */
	public function actionTables(RequestEvent $request, Generator $generator, string $db = 'db') {
		$db = db($db)->pop();
		$generator->allTable(
			$db,
			function($tables) use($db, $request) {
				$request->getResponse()->json($tables);
				$db->push();
			},
			function($data, $e) use($db, $request) {
				$db->push();
				$request->getResponse()->setStatus(500)->json([
					'status' => false,
					'message' => $e->getMessage(),
					'data' => $data,
				]);
			}
		);
	}
	
	/**
	 * 生成MySQL的表模型
	 *
	 * @param RequestEvent $request
	 * @param Generator $generator
	 * @param string $table 表名
	 * @param string $class 表模型的类名(包括命名空间的命名)
	 * @param string $base $class类的父类类名(包括命名空间的命名)
	 * @param string $db MySQL数据库的连接ID
	 * @param bool $isComment 是否使用相应数据表列的注释生成属性标签
	 */
	public function actionModel(RequestEvent $request, Generator $generator, string $table, string $class, string $base = MySQLModel::class, string $db = 'db', bool $isComment = false, bool $isOver = false) {
		if($base !== MySQLModel::class && !is_subclass_of($base, MySQLModel::class)) {
			$class = MySQLModel::class;
			throw new Exception("$base 不是 $class 的子类");
		}
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
				
				$request->getResponse()->json($this->generator($status, $newFile, $oldFile));
			},
			function($data, $e) use($request) {
				$request->getResponse()->setStatus(500)->json([
					'status' => false,
					'message' => $e->getMessage(),
					'data' => $data,
				]);
			}
		);
	}
	
	/**
	 * 生成基于MySQL表模型的控制器
	 *
	 * @param RequestEvent $request
	 * @param Generator $generator
	 * @param string $model 模型类
	 * @param string $class 控制器类
	 * @param string $search 搜索模型类
	 * @param string $path 视图目录
	 * @param string $base 控制器基类
	 * @param string $title 标题
	 * @param bool $isTpl 是否使用tpl模板引擎
	 * @param bool $isOver 是否覆盖
	 */
	public function actionCtrl(RequestEvent $request, Generator $generator, string $model, string $class, string $search, ?string $path = null, string $base = Controller::class, ?string $title = null, bool $isTpl = false, bool $isOver = false) {
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
		
		$rets = [];
		
		$searchFile = '@' . str_replace('\\', '/', $search) . '.php';
		list($status, $newFile, $oldFile) = $generator->generate($this, "{$this->genViewPath}/ctrl-search.php", $searchFile, $params, $isOver);
		$rets[] = $this->generator($status, $newFile, $oldFile);
		
		$classes = preg_split('/[^a-zA-Z0-9]+/', $class, -1, PREG_SPLIT_NO_EMPTY);
		$params['className'] = array_pop($classes);
		$params['namespace'] = implode('\\', $classes);
		$params['isTpl'] = $isTpl;
		
		$ctrlFile = '@' . str_replace('\\', '/', $class) . '.php';
		list($status, $newFile, $oldFile) = $generator->generate($this, "{$this->genViewPath}/ctrl-class.php", $ctrlFile, $params, $isOver);
		$rets[] = $this->generator($status, $newFile, $oldFile);
		
		if(!$params['isJson']) {
			$ext = ($isTpl ? 'tpl' : 'php');
			$suffix = ($isTpl ? '-tpl' : null);
			$params['title'] = ($title ?: substr($params['className'], 0, -10));
			
			list($status, $newFile, $oldFile) = $generator->generate($this, "{$this->genViewPath}/ctrl-index{$suffix}.php", "$path/index.$ext", $params, $isOver);
			$rets[] = $this->generator($status, $newFile, $oldFile);
			list($status, $newFile, $oldFile) = $generator->generate($this, "{$this->genViewPath}/ctrl-form{$suffix}.php", "$path/form.$ext", $params, $isOver);
			$rets[] = $this->generator($status, $newFile, $oldFile);
			list($status, $newFile, $oldFile) = $generator->generate($this, "{$this->genViewPath}/ctrl-create{$suffix}.php", "$path/create.$ext", $params, $isOver);
			$rets[] = $this->generator($status, $newFile, $oldFile);
			list($status, $newFile, $oldFile) = $generator->generate($this, "{$this->genViewPath}/ctrl-update{$suffix}.php", "$path/update.$ext", $params, $isOver);
			$rets[] = $this->generator($status, $newFile, $oldFile);
			list($status, $newFile, $oldFile) = $generator->generate($this, "{$this->genViewPath}/ctrl-view{$suffix}.php", "$path/view.$ext", $params, $isOver);
			$rets[] = $this->generator($status, $newFile, $oldFile);
		}
		
		$request->getResponse()->json($rets);
	}
	
	protected function generator(bool $status, string $newFile, string $oldFile) {
		$n = strlen(ROOT)+1;
		if($status) {
			$_newFile = strncmp($newFile, ROOT . '/', $n) ? $newFile : substr($newFile, $n);
			return [
				'status' => true,
				'message' => $newFile === $oldFile ? "写入文件成功: $_newFile" : "文件已存在: $_newFile",
				'source' => $newFile === $oldFile ? null : file_get_contents($newFile),
				'target' => $newFile === $oldFile ? highlight_file($oldFile, true) : file_get_contents($oldFile),
			];
		} else {
			$_oldFile = strncmp($oldFile, ROOT . '/', $n) ? $oldFile : substr($oldFile, $n);
			return [
				'status' => false,
				'message' => "写入文件失败: $_oldFile",
			];
		}
	}
}
