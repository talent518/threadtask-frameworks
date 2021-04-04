<?php
namespace fwe\console;

class HelpController extends Controller {

	public function runAction(string $route, array $params) {
		echo __METHOD__, PHP_EOL;
	}
}