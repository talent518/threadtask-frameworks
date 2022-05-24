<?php

namespace fwe\base;

interface IAction {
	public function getRoute();

	public function beforeAction(array &$params = []): bool;

	public function afterAction(array &$params = []);

	public function runWithEvent(array &$params = []);

	public function run(array &$params = []);

}
