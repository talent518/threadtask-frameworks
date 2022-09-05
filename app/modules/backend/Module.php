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
	
	public function beforeAction(Action $action, array &$params = []): bool {
		if(!parent::beforeAction($action, $params)) return false;
		
		if($action->controller instanceof StaticController) return true;
		
		$request = $params['request']; /* @var RequestEvent $request */
		
		$route = trim($this->route, '/');
		$ok = (function($user) use($request, $route, $action, &$params) {
			$response = $request->getResponse();
			$redirect = "{$route}/default/login";
			
			if($user instanceof User) {
				if($action->route === $redirect) {
					$response->redirect($request->get['backUrl'] ?? "/$route");
				} else {
					$request->data['user'] = $params['user'] = $user;
					$request->recv();
				}
			} elseif($user) {
				$response->setStatus(500)->end($user);
			} elseif($action->route === $redirect) {
				$request->data['user'] = $params['user'] = null;
				$request->recv();
			} else {
				$uri = urlencode($request->uri);
				$response->redirect("/$redirect?backUrl={$uri}");
			}
		})->bindTo(null);
		
		$request->data = [];
		$auth = ($request->cookies['backend-auth'] ?? false);
		if($auth && ($auth = Crypt::decode($auth, $this->cookieKey, $this->cookieExpire)) !== '') {
			list($uid, $password) = explode("\t", $auth);
			if($uid && $password) {
				cache($this->cacheId)->get(
					$auth,
					(function($user) use($ok) {
						$ok($user);
					})->bindTo(null),
					(function(callable $ok) use($uid, $password) {
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
					})->bindTo(null),
					$this->cacheExpire
				);
			} else {
				$ok(null);
			}
		} else {
			$ok(null);
		}
		
		return true;
	}
	
	public function layoutHandler(callable $ok, RequestEvent $request) {
		$request->data['uri'] = $request->uri;
		$ok($request->data);
	}
}
