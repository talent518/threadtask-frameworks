<?php
namespace app\controllers;

use fwe\base\Controller;
use fwe\http\Request;
use fwe\web\RequestEvent;

class HttpController extends Controller {

	public function actionIndex(RequestEvent $request, string $url, bool $isJson = false) {
		$req = new Request($url);
		$req->addHeader('Connection', 'keep-alive');
		$req->send(function (int $errno, string $error) use ($request, $req, $isJson) {
			$response = $request->getResponse();

			if($errno) {
				$response->setStatus(500);
				if($isJson) {
					$response->json($error);
				} else {
					$response->end($error);
				}
			} elseif($req->responseStatus === null) {
				$response->setStatus(500);
				if($isJson) {
					$response->json('Unknown error');
				} else {
					$response->end('Unknown error');
				}
			} else {
				if($isJson) {
					$response->json($req);
				} else {
					$response->setContentType($req->responseHeaders['Content-Type'] ?? 'text/html; charset=utf-8');
					$response->setStatus($req->responseStatus, $req->responseStatusText);
					$response->end($req->responseData);
				}
			}
		});
		$request->onFree(function () use ($req) {
			$req->free(- 100, 'Cancel');
		});
	}
}
