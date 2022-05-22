<?php
namespace app\modules\backend\controllers;

use app\modules\backend\models\ClazzSearch as Search;
use app\modules\backend\models\Clazz as Model;
use fwe\traits\TplView;
use fwe\web\RequestEvent;

/**
 * 由fwe\web\GeneratorController生成的代码
 */
class ClazzController extends \fwe\base\Controller {
	use TplView;
	
	public function actionIndex(RequestEvent $request, int $page = 1, int $size = 10, string $orderBy = 'cno', bool $isDesc = true) {
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
				$this->render('index', ['model' => $model], function(string $html) use($request) {
					$request->getResponse()->end($html);
				}, $request);
			},
			function($e) use($db, $model, $request) {
				$db->push();
				$request->getResponse()->setStatus(500)->end($e->getMessage());
			}
		);
	}
	
	public function actionCreate(RequestEvent $request, string $backUrl = '') {
		if($request->method !== 'POST') {
			$model = Model::create();
			$model->setScene('create');
			$this->render('create', ['model' => $model, 'data' => null, 'status' => null, 'backUrl' => $backUrl], function(string $html) use($request) {
				$request->getResponse()->end($html);
			}, $request);
		} else {
			$db = db()->pop(false);
			$model = Model::create();
			$model->setScene('create');
			$model->attributes = $request->post;
			$model->save(
				$db,
				function(\stdClass $status) use($db, $request, $backUrl) {
					$request->getResponse()->redirect($status->insertId ? "/{$this->route}view?id={$status->insertId}&backUrl=" . urlencode($backUrl) : ($backUrl ?: "/{$this->route}index"));
				},
				function(\stdClass $status) use($db, $request, $model, $backUrl) {
					$this->render('create', ['model' => $model, 'data' => $status, 'status' => false, 'backUrl' => $backUrl], function(string $html) use($request) {
						$request->getResponse()->end($html);
					}, $request);
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
	
	public function actionUpdate(RequestEvent $request, $id, string $backUrl = '') {
		$db = db()->pop(false);
		Model::findById(
			$db,
			$id,
			function(?Model $row) use($db, $request, $backUrl) {
				if($row === null) {
					$request->getResponse()->setStatus(404)->end('未找到记录');
				} elseif($request->method !== 'POST') {
					$this->render('update', ['model' => $row, 'data' => null, 'status' => null, 'backUrl' => $backUrl], function(string $html) use($request) {
						$request->getResponse()->end($html);
					}, $request);
				} else {
					$row->setScene('update');
					$row->attributes = $request->post;
					$row->save(
						$db,
						function(\stdClass $status) use($request, $row, $backUrl) {
							$request->getResponse()->redirect("/{$this->route}view?id={$row->cno}&backUrl=" . urlencode($backUrl));
						},
						function(\stdClass $status) use($request, $row, $backUrl) {
							$this->render('update', ['model' => $row, 'data' => $status, 'status' => false, 'backUrl' => $backUrl], function(string $html) use($request) {
								$request->getResponse()->end($html);
							}, $request);
						}
					);
				}
			},
			function($e) use($db, $request) {
				$request->getResponse()->setStatus(500)->end($e->getMessage());
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
	
	public function actionDelete(RequestEvent $request, $id, string $backUrl = '') {
		$db = db()->pop(false);
		Model::findById(
			$db,
			$id,
			function(?Model $row) use($db, $request, $backUrl) {
				if($row === null) {
					$request->getResponse()->setStatus(404)->end('未找到记录');
				} else {
					$row->delete(
						$db,
						function(array $data) use($request, $backUrl) {
							$request->getResponse()->redirect($backUrl ?: "/{$this->route}index");
						},
						function($e) use($request) {
							$request->getResponse()->setStatus(500)->end($e->getMessage());
						}
					);
				}
			},
			function($e) use($db, $request) {
				$request->getResponse()->setStatus(500)->end($e->getMessage());
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
	
	public function actionView(RequestEvent $request, $id, string $backUrl = '') {
		$db = db()->pop();
		Model::findById(
			$db,
			$id,
			function(?Model $row) use($request, $backUrl) {
				if($row === null) {
					$request->getResponse()->setStatus(404)->end('未找到记录');
				} else {
					$this->render('view', ['model' => $row, 'backUrl' => $backUrl], function(string $html) use($request) {
						$request->getResponse()->end($html);
					}, $request);
				}
			},
			function($e) use($request) {
				$request->getResponse()->setStatus(500)->end($e->getMessage());
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
