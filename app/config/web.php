<?php
Fwe::setAlias('@app', ROOT . '/app');
Fwe::setAlias('@runtime', ROOT . '/runtime');
Fwe::setAlias('@web', ROOT . '/web');

return [
	'class' => 'fwe\web\Application',
	'id' => 'fwe',
	'name' => 'FWE框架',
	'bootstrap' => ['curl'],
	'components' => [
		'db' => require __DIR__ . '/db.php',
		'redis' => require __DIR__ . '/redis.php',
		'curl' => require __DIR__ . '/curl-component.php',
	],
	'modules' => [
		'gii' => 'fwe\gii\Module',
	]
];
