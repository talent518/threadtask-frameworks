<?php
return [
	'class' => 'fwe\web\Application',
	'id' => 'fwe',
	'name' => 'FWE框架',
	'bootstrap' => [],
	'components' => [
		'db' => require __DIR__ . '/db.php',
		'redis' => require __DIR__ . '/redis.php',
		'curl' => require __DIR__ . '/curl-component.php',
	],
	'modules' => [
		'gii' => 'fwe\gii\Module',
	]
];
