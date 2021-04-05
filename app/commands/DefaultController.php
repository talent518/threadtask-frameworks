<?php
namespace app\commands;

use fwe\base\Controller;

class DefaultController extends Controller {

	/**
	 * Hello World
	 */
	public function actionIndex() {
		echo "Hello World\n";
	}
	
	/**
	 * Hello-World
	 */
	public function actionHelloWorld(string $actionID, string $methodName) {
		echo "Hello-World\nactionID: $actionID\nmethodName: $methodName\n";
	}
}
