<?php
namespace app\test\controllers;

use fwe\console\Controller;

class DefaultController extends Controller {
	public function actionIndex() {
		echo __METHOD__ . "\n";
	}
}

