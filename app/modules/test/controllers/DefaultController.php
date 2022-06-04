<?php
namespace app\modules\test\controllers;

use fwe\base\Action;
use fwe\base\Controller;
use fwe\traits\TplView;
use fwe\web\RequestEvent;

class DefaultController extends Controller {
	use TplView;

	public function beforeAction(Action $action, array &$params = []): bool {
		$this->replaceWhiteSpace = $params['REPLACE_WHITE_SPACE'] ?? null;
		
		return parent::beforeAction($action, $params);
	}
	
	public function actionIndex(RequestEvent $request) {
		$view0 = __METHOD__;
		$view1 = $this->getViewFile("@app/{$this->id}/index");
		$view2 = $this->getViewFile("//{$this->id}/index");
		$view3 = $this->getViewFile("/{$this->id}/index");
		$view4 = $this->getViewFile('index');
		$request->getResponse()->end($this->render('index', compact('view0', 'view1', 'view2', 'view3', 'view4')));
	}

	public function actionTemplate(RequestEvent $request) {
		$get = $request->get;
		$headers = $request->headers;
		$tree = $this->makeTree(mt_rand(2, 5));
		$request->getResponse()->end($this->render('template', compact('request', 'get', 'headers', 'tree')));
	}
	
	protected function makeTree(int $maxLevel, array &$tree = [], int $pid = 0, int $level = 1, int &$ids = 0) {
		$n = mt_rand(2, 5);
		$id = $ids;
		$ids += $n;
		for($i=0; $i<$n; $i++) {
			$id++;
			$name = bin2hex(random_bytes(4));
			$tree[$pid][] = compact('id', 'pid', 'name', 'level');
			if($level < $maxLevel) {
				$this->makeTree($maxLevel, $tree, $id, $level + 1, $ids);
			}
		}
		
		if($pid === 0) {
			return $tree;
		}
	}
}
