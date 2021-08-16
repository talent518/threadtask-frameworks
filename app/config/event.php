<?php
Fwe::setAlias('@app', ROOT . '/app');
Fwe::setAlias('@runtime', ROOT . '/runtime');
Fwe::setAlias('@web', ROOT . '/web');

return [
	'class' => 'fwe\event\Application',
	'id' => 'fwe',
	'name' => 'FWE框架',
	'components' => [
		'db' => require __DIR__ . '/db.php',
		'redis' => require __DIR__ . '/redis.php',
	],
	'modules' => [
		'gii' => 'fwe\gii\Module',
	]
];
