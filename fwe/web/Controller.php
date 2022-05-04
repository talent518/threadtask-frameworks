<?php
namespace fwe\web;

use fwe\base\Action;
use fwe\base\RouteException;

/**
 * RESTful规范的action路由
 */
class Controller extends \fwe\base\Controller {
	public function splitId(string &$id, array &$params) {
		switch($params['request']->method) {
			case 'OPTIONS':
				if($id === '') {
					$methods = ['HEAD', 'GET', 'POST']; // action: index, create
				} elseif(preg_match('/^\d+$/', $id)) {
					$methods = ['HEAD', 'GET', 'PUT', 'DELETE']; // action: view, update, delete
				} elseif(preg_match('/^\d+\/(\w+)$/', $id, $matches) && !in_array($matches[1], ['options', 'index', 'create', 'update', 'delete', 'view'])) {
					$methods = ['HEAD', 'GET', 'PUT'];
				} else {
					$methods = [];
				}
				$methods[] = 'OPTIONS';
				$params['methods'] = $methods;
				$id = 'options';
				break;
			case 'HEAD':
			case 'GET':
				if($id === '') {
					$id = 'index';
				} elseif(preg_match('/^\d+$/', $id)) {
					$params['id'] = (int) $id;
					$id = 'view';
				} elseif(preg_match('/^(\d+)\/(\w+)$/', $id, $matches) && !in_array($matches[2], ['options', 'index', 'create', 'update', 'delete', 'view'])) {
					$params['id'] = (int) $matches[1];
					$id = "get-{$matches[2]}";
				} else {
					throw new RouteException($id, "没有发现符合RESETful规范的操作\"$id\"");
				}
				break;
			case 'POST':
				if($id === '') {
					$id = 'create';
				} else {
					throw new RouteException($id, "没有发现符合RESETful规范的操作\"$id\"");
				}
				break;
			case 'PUT':
				if(preg_match('/^\d+$/', $id)) {
					$params['id'] = (int) $id;
					$id = 'update';
				} elseif(preg_match('/^(\d+)\/(\w+)$/', $id, $matches) && !in_array($matches[2], ['options', 'index', 'create', 'update', 'delete', 'view'])) {
					$params['id'] = (int) $matches[1];
					$id = "set-{$matches[2]}";
				} else {
					throw new RouteException($id, "没有发现符合RESETful规范的操作\"$id\"");
				}
				break;
			case 'DELETE':
				if(preg_match('/^\d+$/', $id)) {
					$params['id'] = (int) $id;
					$id = 'delete';
				} else {
					throw new RouteException($id, "没有发现符合RESETful规范的操作\"$id\"");
				}
				break;
			default:
				throw new RouteException($id, "没有发现符合RESETful规范的操作\"$id\"");
		}
		
		return false;
	}
	
	public function beforeAction(Action $action, array $params = []): bool {
		if(parent::beforeAction($action, $params)) {
			$response = $params['request']->getResponse();
			$response->headers['Access-Control-Allow-Origin'] = ['*'];
			$response->headers['Access-Control-Allow-Headers'] = ['Origin, X-Requested-With, Content-Type, Accept, Authorization'];
			return true;
		} else {
			return false;
		}
	}
	
	public function actionOptions(RequestEvent $request, array $methods) {
		$response = $request->getResponse();
		$response->headers['Access-Control-Allow-Methods'] = $methods;
		$response->end();
	}
}
