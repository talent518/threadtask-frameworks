<?php
$cookieFile = \Fwe::getAlias('@app/runtime/cookie.key');
if(is_file($cookieFile) && filemtime($cookieFile) + 86400 * 30 > time()) {
	$cookieKey = file_get_contents($cookieFile);
} else {
	$cookieKey = bin2hex(random_bytes(8));
	file_put_contents($cookieFile, $cookieKey);
}

return [
	'class' => 'fwe\web\Application',
	'id' => 'web',
	'name' => 'FWE框架',
	'layoutView' => '//layout/main',
	'bootstrap' => [],
	'components' => [
		'db' => require __DIR__ . '/db.php',
		'curl' => [
			'maxThreads' => (($threads = getenv("CURL_THREADS")) > 0 ? (int) $threads : 2),
		],
		'monitor' => 'app\ws\MonBoot',
		'cache2' => 'fwe\cache\Redis',
	],
	'controllerMap' => [
		'generator' => [
			'class' => 'fwe\web\GeneratorController', // 需要安全控制的重构该类即可
			'actionMap' => [
				'index' => [
					'class' => 'fwe\web\RedirectAction',
					'url' => '/static/generator.html',
				],
			],
		],
		'favicon.ico' => [
			'class' => 'fwe\web\StaticController',
			'path' => '@app/static/favicon.ico',
		],
		'static' => [
			'class' => 'fwe\web\StaticController',
			'path' => '@app/static/',
		],
		'dav' => [
			'class' => 'fwe\web\StaticController',
			'path' => '@app/static/dav/',
			'isDav' => true,
			// 'username' => 'admin',
			// 'password' => 'admin8',
		],
	],
	'modules' => [
		'backend' => [
			'class' => 'app\modules\backend\Module',
			'cookieKey' => $cookieKey,
			'cacheId' => 'cache2',
			'controllerMap' => [
				'static' => [
					'class' => 'fwe\web\StaticController',
					'path' => '@app/modules/backend/static/',
				],
			],
		],
		'test' => 'app\modules\test\Module',
	],
];
