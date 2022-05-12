<?php
return [
	'class' => 'fwe\web\Application',
	'id' => 'web',
	'name' => 'FWE框架',
	'layoutView' => '//layout/main',
	'bootstrap' => [],
	'components' => [
		'db' => require __DIR__ . '/db.php',
		'curl' => [
			'class' => 'fwe\curl\Event',
			'verbose' => (getenv('CURL_VERBOSE') ? true : false),
		],
		// 'curl' => [
		// 	'maxThreads' => (($threads = getenv("CURL_THREADS")) > 0 ? (int) $threads : 2),
		// ],
		'monitor' => 'app\ws\MonBoot',
		'cache2' => 'fwe\cache\Redis',
	],
	'statics' => [
		'/favicon.ico' => '@app/static/favicon.ico',
		'/static/' => '@app/static/',
	],
	'controllerMap' => [
		'generator' => 'fwe\web\GeneratorController', // 需要安全控制的重构该类即可
	],
	'modules' => [
		'test' => 'app\modules\test\Module',
	],
];
