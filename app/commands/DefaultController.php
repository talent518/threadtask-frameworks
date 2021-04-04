<?php
namespace app\commands;

use fwe\base\Controller;

class DefaultController extends Controller {
	public function actionIndex() {
		echo "Hello World\n";
	}
}
