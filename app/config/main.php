<?php
Fwe::setAlias('@app', ROOT . '/app');
Fwe::setAlias('@runtime', ROOT . '/runtime');
Fwe::setAlias('@web', ROOT . '/web');

return [
	'class' => 'fwe\console\Application',
	'id' => 'fwe',
	'name' => 'FWE框架',
	// 'runActionMethod' => 'run', // 默认值为: runWithEvent
	'components' => [
		'db' => require __DIR__ . '/db.php',
	],
	'modules' => [
		'gii' => 'fwe\gii\Module',
	]
];