<?php
return [
	'class' => 'fwe\web\Application',
	'id' => 'fwe',
	'name' => 'FWE框架',
	'bootstrap' => [],
	'components' => [
		'db' => require __DIR__ . '/db.php',
		'curl' => [
			'maxThreads' => (($threads = getenv("CURL_THREADS")) > 0 ? (int) $threads : 2),
		],
	],
	'modules' => [
		'gii' => 'fwe\gii\Module',
	],
	'statics' => [
		'/favicon.ico' => '@app/static/favicon.ico',
		'/static/' => '@app/static/',
	],
];
