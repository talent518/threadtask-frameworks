<?php
namespace app\modules\backend;

use fwe\web\RequestEvent;
use app\modules\backend\utils\Crypt;
use app\modules\backend\models\User;
use fwe\base\Action;
use fwe\web\StaticController;

class Module extends \fwe\base\Module {
	public $cookieKey;
	public $cookieExpire = 0;
	public $cookiePath;
	public $cacheId = 'cache';
	public $cacheExpire = 5;
	
	public function init() {
		parent::init();
		
		$this->layoutView = '/layouts/main';
		$this->layoutCall = [$this, 'layoutHandler'];
		
		if($this->cookieKey === null || $this->cookieKey === '') {
			throw new \Exception('必须设置 cookieKey 属性值');
		}
		
		if($this->cookiePath === null || $this->cookiePath === '') {
			$this->cookiePath = '/' . rtrim($this->getRoute(), '/');
		}
	}
	
	public function logout(RequestEvent $request) {
		cache($this->cacheId)->del($request->cookies['backend-auth'], function() use($request) {
			$backUrl = urlencode($request->get['backUrl'] ?? '');
			$request->getResponse()->setCookie('backend-auth', '', $this->cookieExpire, $this->cookiePath)->redirect("/{$this->route}default/login?backUrl={$backUrl}");
		});
	}
	
	public function login(RequestEvent $request, User $user) {
		$auth = "{$user->uid}\t{$user->password}";
		
		cache($this->cacheId)->del($auth, function() use($request, $auth) {
			$auth = Crypt::encode($auth, $this->cookieKey, $this->cookieExpire);
			$backUrl = $request->get['backUrl'] ?? '';
			
			$request->getResponse()->setCookie('backend-auth', $auth, $this->cookieExpire, $this->cookiePath)->redirect($backUrl ?: "/{$this->route}");
		});
	}
	
	public function runAction(RequestEvent $request, Action $action, array $params) {
		if($action->controller instanceof StaticController) {
			$ret = $action->run($params);
			$action->afterAction($params);
			return $ret;
		}
		
		$ok = function($user) use($request, $action, $params) {
			$events = \Fwe::$app->events;
			$response = $request->getResponse();
			$redirect = "{$this->route}default/login";
			
			if($user instanceof User) {
				if($action->route === $redirect) {
					$route = trim($this->route, '/');
					$response->redirect("/$route");
					$ret = null;
				} else {
					$request->data['user'] = $params['user'] = $user;
					$ret = $action->run($params);
					$action->afterAction($params);
				}
			} elseif($user) {
				$response->setStatus(500)->end($user);
				$ret = null;
			} elseif($action->route === $redirect) {
				$request->data['user'] = $params['user'] = null;
				$ret = $action->run($params);
				$action->afterAction($params);
			} else {
				$response->redirect("/$redirect");
				$ret = null;
			}
			
			if(is_string($ret)) {
				$response->end($ret);
			} elseif(!$response->isHeadSent() && $events == \Fwe::$app->events) {
				$key = $request->getKey();
				\Fwe::$app->error("Not Content in the route({$key}): {$action->route}", 'web');
				$response->setStatus(501);
				$response->end();
			}
		};
		$request->data = [];
		$auth = ($request->cookies['backend-auth'] ?? false);
		if($auth && ($auth = Crypt::decode($auth, $this->cookieKey, $this->cookieExpire)) !== '') {
			list($uid, $password) = explode("\t", $auth);
			if($uid && $password) {
				cache($this->cacheId)->get(
					$auth,
					function($user) use($ok) {
						$ok($user);
					},
					function(callable $ok) use($uid, $password) {
						$db = db()->pop();
						User::find()->whereArgs('and', ['uid'=>$uid, 'password'=>$password])->fetchOne($db, 'user', function(?User $user) use($ok) {
							$ok($user);
							return $user;
						})->goAsync(
							function($user) use($db) {
								$db->push();
							},
							function($e) use($db, $ok) {
								$db->push();
								$ok($e->getMessage());
							}
						);
					},
					$this->cacheExpire
				);
			} else {
				$ok(null);
			}
		} else {
			$ok(null);
		}
	}
	
	public function layoutHandler(callable $ok, RequestEvent $request) {
		$request->data['uri'] = $request->uri;
		$ok($request->data);
	}
}
