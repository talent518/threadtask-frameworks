<?php
use fwe\base\Application;

return [
	'class' => 'fwe\console\Application',
	'id' => 'main',
	'name' => 'FWE框架',
	'logLevel' => (($logLevel = getenv('LOG_LEVEL')) > 0) ? ($logLevel & Application::LOG_ALL) : Application::LOG_ERROR|Application::LOG_WARN|Application::LOG_INFO,
	'bootstrap' => [],
	'components' => [
		'db' => require __DIR__ . '/db.php',
		'curl' => [
			'maxThreads' => (($threads = getenv("CURL_THREADS")) > 0 ? (int) $threads : 2),
		],
	],
];
