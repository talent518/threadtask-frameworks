<?php
echo "<?php\n";

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

class <?=$className?> extends \<?=$base?> {
	public function actionIndex(RequestEvent $request, int $page = 1, int $size = 10) {
		$db = db()->pop();
		$model = Search::create();
		$model->attributes = $request->get;
		$model->page = $page;
		$model->size = $size;
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
	
	public function actionCreate(RequestEvent $request) {
		if($request->method !== 'POST') {
<?php if($isJson):?>
			$request->getResponse()->json(['status' => false, 'message' => '必须使用POST提交数据', 'data' => null]);
<?php else:?>
			$model = Model::create();
			$model->setScene('create');
			$request->getResponse()->end($this->render('create', ['model' => $model, 'data' => null, 'status' => null]));
<?php endif;?>
		} else {
			$db = db()->pop();
			$model = Model::create();
			$model->setScene('create');
			$model->attributes = $request->post;
			$model->save(
				$db,
				function(\stdClass $status) use($db, $request) {
<?php if($isJson):?>
					$request->getResponse()->json(['status' => true, 'message' => '创建成功', 'data' => $status]);
<?php else:?>
					$request->getResponse()->redirect($status->insertId ? "/{$this->route}view?id={$status->insertId}" : "/{$this->route}index");
<?php endif;?>
				},
				function(\stdClass $status) use($db, $request, $model) {
<?php if($isJson):?>
					$request->getResponse()->json(['status' => false, 'message' => $e->getMessage(), 'data' => $model]);
<?php else:?>
					$request->getResponse()->end($this->render('create', ['model' => $model, 'data' => $status, 'status' => false]));
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
	
	public function actionUpdate(RequestEvent $request, <?=$idFuncParams?>) {
		$db = db()->pop();
		Model::findById(
			$db,
			<?=$idFuncParamValues?>,
			function(?Model $row) use($db, $request) {
				if($row === null) {
<?php if($isJson):?>
					$request->getResponse()->json(['status' => false, 'message' => '未找到记录', 'data' => null]);
<?php else:?>
					$request->getResponse()->setStatus(404)->end('未找到记录');
<?php endif;?>
				} elseif($request->method !== 'POST') {
<?php if($isJson):?>
					$request->getResponse()->json(['status' => false, 'message' => '必须使用POST提交数据', 'data' => null]);
<?php else:?>
					$request->getResponse()->end($this->render('update', ['model' => $row, 'data' => null, 'status' => null]));
<?php endif;?>
				} else {
					$row->setScene('update');
					$row->attributes = $request->post;
					$row->save(
						$db,
						function(\stdClass $status) use($request, $row) {
<?php if($isJson):?>
							$request->getResponse()->json(['status' => true, 'message' => '更新成功', 'data' => $status]);
<?php else:?>
							$request->getResponse()->redirect("/{$this->route}view?<?=$generator->genKeyForModel($model, 'row', false)?>");
<?php endif;?>
						},
						function(\stdClass $status) use($request, $row) {
<?php if($isJson):?>
							$request->getResponse()->json(['status' => false, 'message' => $e->getMessage(), 'data' => $model]);
<?php else:?>
							$request->getResponse()->end($this->render('update', ['model' => $row, 'data' => $status, 'status' => false]));
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
	
	public function actionDelete(RequestEvent $request, <?=$idFuncParams?>) {
		$db = db()->pop();
		Model::findById(
			$db,
			<?=$idFuncParamValues?>,
			function(?Model $row) use($db, $request) {
				if($row === null) {
<?php if($isJson):?>
					$request->getResponse()->json(['status' => false, 'message' => '未找到记录', 'data' => null]);
<?php else:?>
					$request->getResponse()->setStatus(404)->end('未找到记录');
<?php endif;?>
				} else {
					$row->delete(
						$db,
						function(array $data) use($request) {
<?php if($isJson):?>
							$request->getResponse()->json(['status' => true, 'message' => '删除成功', 'data' => $status]);
<?php else:?>
							$request->getResponse()->redirect("/{$this->route}index");
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
	
	public function actionView(RequestEvent $request, <?=$idFuncParams?>) {
		$db = db()->pop();
		Model::findById(
			$db,
			<?=$idFuncParamValues?>,
			function(?Model $row) use($request) {
				if($row === null) {
<?php if($isJson):?>
					$request->getResponse()->json(['status' => false, 'message' => '未找到记录', 'data' => null]);
<?php else:?>
					$request->getResponse()->setStatus(404)->end('未找到记录');
<?php endif;?>
				} else {
<?php if($isJson):?>
					$request->getResponse()->json(['status' => true, 'message' => '找到记录', 'data' => $row]);
<?php else:?>
					$request->getResponse()->end($this->render('view', ['model' => $row]));
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
