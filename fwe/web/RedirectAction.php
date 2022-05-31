<?php
namespace fwe\web;

use fwe\base\Action;
use fwe\base\Exception;

class RedirectAction extends Action {
	public $status = 302;
	public $url;
	
	public function init() {
		parent::init();
		
		if($this->url === null || $this->url === '') {
			throw new Exception('path property is not empty');
		}
	}
	
	public function runWithParams(RequestEvent $request) {
		$request->getResponse()->setStatus($this->status)->redirect($this->url);
	}
}

