<?php
namespace app\modules\test\controllers;

use fwe\console\Controller;
use fwe\web\RequestEvent;

class DefaultController extends Controller {
	public function actionIndex(RequestEvent $request) {
		$view0 = __METHOD__;
		$view1 = $this->getViewFile("@app/{$this->id}/index");
		$view2 = $this->getViewFile("//{$this->id}/index");
		$view3 = $this->getViewFile("/{$this->id}/index");
		$view4 = $this->getViewFile('index');
		$request->getResponse()->text("$view0\n$view1\n$view2\n$view3\n$view4\n");
	}
}
