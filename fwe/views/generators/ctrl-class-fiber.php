<?php
echo "<?php\n";

/**
 * @var $this \fwe\base\Controller 控制器对象
 * @var $model string 数据模块类全名
 * @var $search string 搜索模块类全名
 * @var $class string 控制器类全名
 * @var $base string 控制器父类全名
 * @var $namespace string 控制器命名空间
 * @var $className string 控制器类名
 * @var $isJson bool 是否作为JSON响应
 * @var $isRestful bool 是否作为RESTful规范
 * @var $generator \fwe\db\Generator 生成器对象
 */

$priKeys = $model::priKeys();
$n = count($priKeys);
if($n) {
	if($n > 1) {
		$idFuncParams = '$' . implode(', $', $priKeys);
		$_paramValues = [];
		foreach($priKeys as $key) {
			$_paramValues[] = "'$key' => \$$key";
		}
		$idFuncParamValues = '[' . implode(', ', $_paramValues) . ']';
	} else {
		$idFuncParams = $idFuncParamValues = '$id';
	}
} else {
	$idFuncParams = $idFuncParamValues = '$unknown';
}

?>
namespace <?=$namespace?>;

use <?=$search?> as Search;
use <?=$model?> as Model;
<?php if(!$isJson && $isTpl):?>
use fwe\traits\TplView;
<?php endif;?>
use fwe\web\RequestEvent;
use fwe\fibers\MySQLFiber;
use fwe\fibers\UtilFiber;
use fwe\base\Action;

/**
 * 由<?=get_class($this)?>生成的代码
 */
class <?=$className?> extends \<?=$base?> {
<?php if(!$isJson && $isTpl):?>
	use TplView;
	
<?php endif;?>
	public function runAction(Action $action, array &$params = []) {
		$request = $params['request'];
		UtilFiber::web($request, function() use($request, $action, &$params) {
			$response = $request->getResponse();
			try {
				$ret = \Fwe::invoke($action->callback, $params, $action->funcName);
				if(is_string($ret)) $response->end($ret);
				elseif(is_array($ret) || is_object($ret)) $response->json($ret);
				elseif(is_int($ret)) $response->setStatus($ret)->json($ret);
			} catch(\Throwable $e) {
				\Fwe::$app->handleException($e, 'fiber');
<?php if($isJson):?>
				$response->json(['status' => false, 'message' => $e->getMessage(), 'data' => null]);
<?php else:?>
				$response->setStatus(500)->end($e->getMessage());
<?php endif;?>
			}
		});
	}
	
	public function actionIndex(RequestEvent $request, int $page = 1, int $size = 10, string $orderBy = '<?=reset($priKeys)?>', bool $isDesc = true) {
		$model = Search::create();
		$model->attributes = $request->get;
		$model->page = $page;
		$model->size = $size;
		$model->orderBy = $orderBy;
		$model->isDesc = $isDesc;
		
		try {
			$db = MySQLFiber::pop();
			$model->search($db);
<?php if($isJson):?>
			$request->getResponse()->json(['status' => true, 'message' => 'OK', 'data' => $model]);
<?php else:?>
			$this->render('index', ['model' => $model], function(string $html) use($request) {
				$request->getResponse()->end($html);
			}, $request);
<?php endif;?>
		} catch(\Throwable $e) {
<?php if($isJson):?>
			$request->getResponse()->json(['status' => false, 'message' => $e->getMessage(), 'data' => $model]);
<?php else:?>
			$request->getResponse()->setStatus(500)->end($e->getMessage());
<?php endif;?>
		}
	}
	
	public function actionCreate(RequestEvent $request<?php if(!$isJson):?>, string $backUrl = ''<?php endif?>) {
		if(<?php if($isRestful):?>$request->bodylen === 0<?php else:?>$request->method !== 'POST'<?php endif;?>) {
<?php if($isJson):?>
			$request->getResponse()->json(['status' => false, 'message' => '必须使用POST提交数据', 'data' => null]);
<?php else:?>
			$model = Model::create();
			$model->setScene('create');
			$this->render('create', ['model' => $model, 'data' => null, 'status' => null, 'backUrl' => $backUrl], function(string $html) use($request) {
				$request->getResponse()->end($html);
			}, $request);
<?php endif;?>
		} else {
			$model = Model::create();
			$model->setScene('create');
			$model->attributes = $request->post;
			
			$db = MySQLFiber::pop(false);
			$status = $model->save($db);
			if($status) {
<?php if($isJson):?>
				$request->getResponse()->json(['status' => true, 'message' => '创建成功', 'data' => null]);
<?php else:?>
				$request->getResponse()->redirect($status->insertId ? "/{$this->route}view?id={$status->insertId}&backUrl=" . urlencode($backUrl) : ($backUrl ?: "/{$this->route}index"));
<?php endif;?>
			} else {
<?php if($isJson):?>
				$request->getResponse()->json(['status' => false, 'message' => '数据验证未通过', 'errors' => $model->getErrors(), 'data' => $model]);
<?php else:?>
				$this->render('create', ['model' => $model, 'data' => null, 'status' => false, 'backUrl' => $backUrl], function(string $html) use($request) {
					$request->getResponse()->end($html);
				}, $request);
<?php endif;?>
			}
		}
	}
	
	public function actionUpdate(RequestEvent $request, <?=$idFuncParams?><?php if(!$isJson):?>, string $backUrl = ''<?php endif?>) {
<?php if($isJson):?>
		if($request->bodylen === 0) {
			$request->getResponse()->json(['status' => false, 'message' => '必须使用<?php if($isRestful):?>PUT<?php else:?>POST<?php endif;?>提交数据', 'errors' => [], 'data' => null]);
			return;
		}
<?php endif;?>
		$db = MySQLFiber::pop(false);
		$row = Model::findById($db, <?=$idFuncParamValues?>); /* @var $row Model */
		if($row === null) {
<?php if($isJson):?>
			$request->getResponse()->json(['status' => false, 'message' => '未找到记录', 'data' => null]);
<?php else:?>
			$request->getResponse()->setStatus(404)->end('未找到记录');
<?php endif;?>
<?php if(!$isJson):?>
		} elseif($request->method !== 'POST') {
			$this->render('update', ['model' => $row, 'data' => null, 'status' => null, 'backUrl' => $backUrl], function(string $html) use($request) {
				$request->getResponse()->end($html);
			}, $request);
<?php endif;?>
		} else {
			$row->setScene('update');
			$row->attributes = $request->post;
			$status = $row->save($db);
			if($status) {
<?php if($isJson):?>
				$request->getResponse()->json(['status' => true, 'message' => '更新成功', 'data' => $status]);
<?php else:?>
				$request->getResponse()->redirect("/{$this->route}view?<?=$generator->genKeyForModel($model, 'row', false)?>&backUrl=" . urlencode($backUrl));
<?php endif;?>
			} else {
<?php if($isJson):?>
				$request->getResponse()->json(['status' => false, 'message' => '数据验证未通过', 'errors' => $row->getErrors(), 'data' => $row]);
<?php else:?>
				$this->render('update', ['model' => $row, 'data' => null, 'status' => false, 'backUrl' => $backUrl], function(string $html) use($request) {
					$request->getResponse()->end($html);
				}, $request);
<?php endif;?>
			}
		}
	}
	
	public function actionDelete(RequestEvent $request, <?=$idFuncParams?><?php if(!$isJson):?>, string $backUrl = ''<?php endif?>) {
		$db = MySQLFiber::pop(false);
		$row = Model::findById($db, <?=$idFuncParamValues?>); /* @var $row Model */
		if($row === null) {
<?php if($isJson):?>
			$request->getResponse()->json(['status' => false, 'message' => '未找到记录', 'errors' => [], 'data' => null]);
<?php else:?>
			$request->getResponse()->setStatus(404)->end('未找到记录');
<?php endif;?>
		} else {
<?php if($isJson):?>
			$data = $row->delete($db);
			$request->getResponse()->json(['status' => true, 'message' => '删除成功', 'data' => $data]);
<?php else:?>
			$row->delete($db);
			$request->getResponse()->redirect($backUrl ?: "/{$this->route}index");
<?php endif;?>
		}
	}
	
	public function actionView(RequestEvent $request, <?=$idFuncParams?><?php if(!$isJson):?>, string $backUrl = ''<?php endif?>) {
		$db = MySQLFiber::pop();
		$row = Model::findById($db, <?=$idFuncParamValues?>); /* @var $row Model */
		if($row === null) {
<?php if($isJson):?>
			$request->getResponse()->json(['status' => false, 'message' => '未找到记录', 'errors' => [], 'data' => null]);
<?php else:?>
			$request->getResponse()->setStatus(404)->end('未找到记录');
<?php endif;?>
		} else {
<?php if($isJson):?>
			$request->getResponse()->json(['status' => true, 'message' => '找到记录', 'data' => $row]);
<?php else:?>
			$this->render('view', ['model' => $row, 'backUrl' => $backUrl], function(string $html) use($request) {
				$request->getResponse()->end($html);
			}, $request);
<?php endif;?>
		}
	}
}
