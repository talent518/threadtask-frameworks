<?php
namespace app\modules\backend\controllers;

use fwe\base\Controller;
use fwe\traits\TplView;
use fwe\web\RequestEvent;
use app\modules\backend\models\Login;

class DefaultController extends Controller {
	use TplView;
	
	public function actionIndex(RequestEvent $request) {
		$this->render('index', [], function(string $html) use($request) {
			$request->getResponse()->end($html);
		}, $request);
	}
	
	public function actionLogin(RequestEvent $request, array $__params__, bool $isEmail = false, string $backUrl = '') {
		$model = Login::create();
		$model->setScene($isEmail ? 'email' : 'username');
		$model->attributes = $__params__;
		if($request->method === 'POST') {
			$model->validate(function(int $ok, ?string $error = null) use($model, $request, $backUrl) {
				if($error !== null) {
					$request->getResponse()->setStatus(500)->end($error);
				} elseif($ok > 0) {
					$this->render('login', ['model' => $model, 'backUrl' => $backUrl], function(string $html) use($request) {
						$request->getResponse()->end($html);
					}, $request);
				} else {
					$this->module->login($request, $model->user);
				}
			}, true);
		} else {
			$this->render('login', ['model' => $model, 'backUrl' => $backUrl], function(string $html) use($request) {
				$request->getResponse()->end($html);
			}, $request);
		}
	}
	
	public function actionLogout(RequestEvent $request) {
		$this->module->logout($request);
	}
}
