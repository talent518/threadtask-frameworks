<?php
Fwe::setAlias('@app', ROOT . '/app');
Fwe::setAlias('@runtime', ROOT . '/runtime');
Fwe::setAlias('@web', ROOT . '/web');

return [
	'class' => 'fwe\event\Application',
	'id' => 'fwe',
	'name' => 'FWE框架'
];
