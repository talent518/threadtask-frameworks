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
	public function actionHelloWorld() {
		echo "Hello-World\n";
	}
}
