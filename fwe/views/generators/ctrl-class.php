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
use fwe\web\RequestEvent;

/**
 * 由<?=get_class($this)?>生成的代码
 */
class <?=$className?> extends \<?=$base?> {
	public function actionIndex(RequestEvent $request, int $page = 1, int $size = 10, string $orderBy = '<?=reset($priKeys)?>', bool $isDesc = true) {
		$db = db()->pop();
		$model = Search::create();
		$model->attributes = $request->get;
		$model->page = $page;
		$model->size = $size;
		$model->orderBy = $orderBy;
		$model->isDesc = $isDesc;
		$model->search(
			$db,
			function() use($db, $model, $request) {
				$db->push();
<?php if($isJson):?>
				$request->getResponse()->json(['status' => true, 'message' => 'OK', 'data' => $model]);
<?php else:?>
				$request->getResponse()->end($this->render('index', ['model' => $model]));
<?php endif;?>
			},
			function($e) use($db, $model, $request) {
				$db->push();
<?php if($isJson):?>
				$request->getResponse()->json(['status' => false, 'message' => $e->getMessage(), 'data' => $model]);
<?php else:?>
				$request->getResponse()->setStatus(500)->end($e->getMessage());
<?php endif;?>
			}
		);
	}
	
	public function actionCreate(RequestEvent $request<?php if(!$isJson):?>, string $backUrl = ''<?php endif?>) {
		if(<?php if($isRestful):?>$request->bodylen === 0<?php else:?>$request->method !== 'POST'<?php endif;?>) {
<?php if($isJson):?>
			$request->getResponse()->json(['status' => false, 'message' => '必须使用POST提交数据', 'data' => null]);
<?php else:?>
			$model = Model::create();
			$model->setScene('create');
			$request->getResponse()->end($this->render('create', ['model' => $model, 'data' => null, 'status' => null, 'backUrl' => $backUrl]));
<?php endif;?>
		} else {
			$db = db()->pop();
			$model = Model::create();
			$model->setScene('create');
			$model->attributes = $request->post;
			$model->save(
				$db,
				function(\stdClass $status) use($db, $request<?php if(!$isJson):?>, $backUrl<?php endif?>) {
<?php if($isJson):?>
					$request->getResponse()->json(['status' => true, 'message' => '创建成功', 'data' => $status]);
<?php else:?>
					$request->getResponse()->redirect($status->insertId ? "/{$this->route}view?id={$status->insertId}&backUrl=" . urlencode($backUrl) : ($backUrl ?: "/{$this->route}index"));
<?php endif;?>
				},
				function(\stdClass $status) use($db, $request, $model<?php if(!$isJson):?>, $backUrl<?php endif?>) {
<?php if($isJson):?>
					$request->getResponse()->json(['status' => false, 'message' => $status->error ?? '数据验证未通过', 'errors' => $model->getErrors(), 'data' => $model]);
<?php else:?>
					$request->getResponse()->end($this->render('create', ['model' => $model, 'data' => $status, 'status' => false, 'backUrl' => $backUrl]));
<?php endif;?>
				}
			);
			if($db->getEvents()) {
				$db->goAsync(
					function() use($db) {
						$db->push();
					},
					function() use($db) {
						$db->push();
					}
				);
			} else {
				$db->push();
			}
		}
	}
	
	public function actionUpdate(RequestEvent $request, <?=$idFuncParams?><?php if(!$isJson):?>, string $backUrl = ''<?php endif?>) {
		if(<?php if($isRestful):?>$request->bodylen === 0<?php else:?>$request->method !== 'POST'<?php endif;?>) {
<?php if($isJson):?>
			$request->getResponse()->json(['status' => false, 'message' => '必须使用<?php if($isRestful):?>PUT<?php else:?>POST<?php endif;?>提交数据', 'errors' => [], 'data' => null]);
<?php else:?>
			$request->getResponse()->end($this->render('update', ['model' => null, 'data' => null, 'status' => null, 'backUrl' => $backUrl]));
<?php endif;?>
			return;
		}
		$db = db()->pop();
		Model::findById(
			$db,
			<?=$idFuncParamValues?>,
			function(?Model $row) use($db, $request<?php if(!$isJson):?>, $backUrl<?php endif?>) {
				if($row === null) {
<?php if($isJson):?>
					$request->getResponse()->json(['status' => false, 'message' => '未找到记录', 'data' => null]);
<?php else:?>
					$request->getResponse()->setStatus(404)->end('未找到记录');
<?php endif;?>
				} else {
					$row->setScene('update');
					$row->attributes = $request->post;
					$row->save(
						$db,
						function(\stdClass $status) use($request, $row<?php if(!$isJson):?>, $backUrl<?php endif?>) {
<?php if($isJson):?>
							$request->getResponse()->json(['status' => true, 'message' => '更新成功', 'data' => $status]);
<?php else:?>
							$request->getResponse()->redirect("/{$this->route}view?<?=$generator->genKeyForModel($model, 'row', false)?>&backUrl=" . urlencode($backUrl));
<?php endif;?>
						},
						function(\stdClass $status) use($request, $row<?php if(!$isJson):?>, $backUrl<?php endif?>) {
<?php if($isJson):?>
							$request->getResponse()->json(['status' => false, 'message' => $status->error ?? '数据验证未通过', 'errors' => $row->getErrors(), 'data' => $row]);
<?php else:?>
							$request->getResponse()->end($this->render('update', ['model' => $row, 'data' => $status, 'status' => false, 'backUrl' => $backUrl]));
<?php endif;?>
						}
					);
				}
			},
			function($e) use($db, $request) {
<?php if($isJson):?>
				$request->getResponse()->json(['status' => false, 'message' => $e->getMessage(), 'data' => null]);
<?php else:?>
				$request->getResponse()->setStatus(500)->end($e->getMessage());
<?php endif;?>
			}
		)->goAsync(
			function() use($db) {
				$db->push();
			},
			function() use($db) {
				$db->push();
			}
		);
	}
	
	public function actionDelete(RequestEvent $request, <?=$idFuncParams?><?php if(!$isJson):?>, string $backUrl = ''<?php endif?>) {
		$db = db()->pop();
		Model::findById(
			$db,
			<?=$idFuncParamValues?>,
			function(?Model $row) use($db, $request<?php if(!$isJson):?>, $backUrl<?php endif?>) {
				if($row === null) {
<?php if($isJson):?>
					$request->getResponse()->json(['status' => false, 'message' => '未找到记录', 'errors' => [], 'data' => null]);
<?php else:?>
					$request->getResponse()->setStatus(404)->end('未找到记录');
<?php endif;?>
				} else {
					$row->delete(
						$db,
						function(array $data) use($request<?php if(!$isJson):?>, $backUrl<?php endif?>) {
<?php if($isJson):?>
							$request->getResponse()->json(['status' => true, 'message' => '删除成功', 'data' => $data]);
<?php else:?>
							$request->getResponse()->redirect($backUrl ?: "/{$this->route}index");
<?php endif;?>
						},
						function($e) use($request) {
<?php if($isJson):?>
							$request->getResponse()->json(['status' => false, 'message' => $e->getMessage(), 'data' => null]);
<?php else:?>
							$request->getResponse()->setStatus(500)->end($e->getMessage());
<?php endif;?>
						}
					);
				}
			},
			function($e) use($db, $request) {
<?php if($isJson):?>
				$request->getResponse()->json(['status' => false, 'message' => $e->getMessage(), 'data' => null]);
<?php else:?>
				$request->getResponse()->setStatus(500)->end($e->getMessage());
<?php endif;?>
			}
		)->goAsync(
			function() use($db) {
				$db->push();
			},
			function() use($db) {
				$db->push();
			}
		);
	}
	
	public function actionView(RequestEvent $request, <?=$idFuncParams?><?php if(!$isJson):?>, string $backUrl = ''<?php endif?>) {
		$db = db()->pop();
		Model::findById(
			$db,
			<?=$idFuncParamValues?>,
			function(?Model $row) use($request<?php if(!$isJson):?>, $backUrl<?php endif?>) {
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
					$request->getResponse()->end($this->render('view', ['model' => $row, 'backUrl' => $backUrl]));
<?php endif;?>
				}
			},
			function($e) use($request) {
<?php if($isJson):?>
				$request->getResponse()->json(['status' => false, 'message' => $e->getMessage(), 'data' => null]);
<?php else:?>
				$request->getResponse()->setStatus(500)->end($e->getMessage());
<?php endif;?>
			}
		)->goAsync(
			function() use($db) {
				$db->push();
			},
			function() use($db) {
				$db->push();
			}
		);
	}
}
