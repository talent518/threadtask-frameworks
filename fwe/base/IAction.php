<?php

namespace fwe\base;

interface IAction {

	public function free();

	public function getRoute();

	public function getParams();

	public function beforeAction();

	public function afterAction();

	public function runWithEvent(array $params = []);

	public function run(array $params = []);

}
