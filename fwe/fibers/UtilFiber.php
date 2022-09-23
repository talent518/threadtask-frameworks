<?php
namespace fwe\fibers;

use fwe\base\Model;
use fwe\curl\IRequest;
use fwe\http\Request;
use fwe\web\RequestEvent;

abstract class UtilFiber {

	public static function web(RequestEvent $request, callable $cb, ...$args) {
		$fiber = new \Fiber($cb);
		$request->onFree(function () use ($fiber) {
			if($fiber->isSuspended()) {
				$fiber->throw(new Exception('RequestEvent onFree fiber'));
			}
		});
		$fiber->start(...$args);
	}

	public static function curl(IRequest $req) {
		$fiber = \Fiber::getCurrent();
		curl()->make($req, function ($res, $req) use ($fiber) {
			if($res->errno) {
				$fiber->throw(new Exception($res->error, $res));
			} else if($res->status === null) {
				$fiber->throw(new Exception('Unknown error', $res));
			} else {
				$fiber->resume($res);
			}
		});

		return $fiber->suspend();
	}

	public static function http(Request $req, int $keepAlive = 0) {
		$fiber = \Fiber::getCurrent();
		$req->send(function (int $errno, string $error) use ($fiber) {
			if($errno) {
				$fiber->throw(new Exception($error, null, $errno));
			} else {
				$fiber->resume($error);
			}
		}, $keepAlive);
		return $fiber->suspend();
	}
	
	public static function validate(Model $model, bool $isPerOne = false, bool $isOnly = false, ...$args) {
		$retObj = new \stdClass();
		$retObj->isReturn = false;
		$retObj->value = null;
		$fiber = \Fiber::getCurrent();
		$model->validate(function (int $n, ?string $errstr = null) use ($fiber, $retObj) {
			if($errstr) {
				$fiber->throw(new Exception($errstr, null, $n));
			} elseif($fiber->isSuspended()) {
				$fiber->resume(!$n);
			} else {
				$retObj->isReturn = true;
				$retObj->value = !$n;
			}
		}, $isPerOne, $isOnly, ...$args);
		return $retObj->isReturn ? $retObj->value : $fiber->suspend();
	}
	
	public static function cache(string $key, callable $set, int $expire = 0, string $id = 'cache') {
		$retObj = new \stdClass();
		$retObj->isReturn = false;
		$retObj->value = null;
		$fiber = \Fiber::getCurrent();
		cache($id)->get(
			$key,
			function($value) use($fiber, $retObj) {
				if($fiber->isSuspended()) {
					$fiber->resume($value);
				} else {
					$retObj->isReturn = true;
					$retObj->value = $value;
				}
			},
			function(callable $ok) use($set) {
				(new \Fiber($set))->start($ok);
			},
			$expire
		);
		
		return $retObj->isReturn ? $retObj->value : $fiber->suspend();
	}
}
