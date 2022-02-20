<?php
return [
	'class' => 'fwe\console\Application',
	'id' => 'fwe',
	'name' => 'FWE框架',
	'bootstrap' => [],
	'components' => [
		'db' => require __DIR__ . '/db.php',
	]
];
