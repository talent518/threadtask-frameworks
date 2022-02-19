<?php
return [
	'class' => 'fwe\console\Application',
	'id' => 'fwe',
	'name' => 'FWE框架',
	'bootstrap' => ['curl'],
	'components' => [
		'db' => require __DIR__ . '/db.php',
		'redis' => require __DIR__ . '/redis.php',
		'curl' => require __DIR__ . '/curl-component.php',
	]
];