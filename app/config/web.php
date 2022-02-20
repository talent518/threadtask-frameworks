<?php
return [
	'class' => 'fwe\web\Application',
	'id' => 'fwe',
	'name' => 'FWE框架',
	'bootstrap' => [],
	'components' => [
		'db' => require __DIR__ . '/db.php',
	],
	'modules' => [
		'gii' => 'fwe\gii\Module',
	]
];
