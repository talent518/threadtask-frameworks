<?php
namespace app\controllers;

use fwe\base\Controller;
use fwe\web\RequestEvent;
use fwe\db\IEvent;

class DefaultController extends Controller {
	public function actionIndex() {
		return __METHOD__ . PHP_EOL;
	}
	public function actionInfo(RequestEvent $request) {
		$response = $request->getResponse();
		$response->setContentType('text/plain; charset=utf-8');
		$response->write(json_encode($request, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT));
		$response->write("\r\n\r\n");
		$response->end(json_encode($response, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT));
	}
	public function actionTables(RequestEvent $request) {
		$t = microtime(true);
		db()->pop()->asyncQuery("SHOW TABLES", ['style'=>IEvent::FETCH_COLUMN_ALL])->asyncQuery("SELECT sleep(1)", ['style'=>IEvent::FETCH_ONE])->asyncQuery("SHOW GLOBAL VARIABLES LIKE '%timeout%'", ['type'=>IEvent::TYPE_OBJ, 'style'=>IEvent::FETCH_ALL])->goAsync(function($tables, $sleep, $variables) use($t, $request) {
			$t = microtime(true) - $t;
			$response = $request->getResponse();
			$response->setContentType('text/plain; charset=utf-8');
			$response->end(json_encode(compact('tables', 'sleep', 'variables', 't'), JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT));
		}, function($data) use($t) {
			$t = microtime(true) - $t;
			$response = $request->getResponse();
			$response->setContentType('text/plain; charset=utf-8');
			$response->end(json_encode(compact('data', 't'), JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT));
			return false;
		});
	}
}
