<?php
namespace app\controllers;

use fwe\base\Controller;
use fwe\web\RequestEvent;
use fwe\curl\Request;

class CurlController extends Controller {
	public function actionIndex(RequestEvent $request, string $url, bool $isJson = false) {
		$req = new Request($url);
		curl()->make($req, function($res, $req) use($request, $isJson) {
			$response = $request->getResponse();

			if($isJson) {
				$res = $res->properties;
				$req = $req->properties;
				$response->setContentType('application/json; charset=utf-8');
				$response->end(json_encode(compact('req', 'res'), JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
			} else {
				$response->headers = $res->headers;
				$response->setStatus($res->status, $res->statusText);
				$response->end($res->data);
			}
		});
	}
}
