<?php
return [
	'class' => 'fwe\curl\Application',
	'id' => 'curl',
	'name' => 'FWE框架',
	'verbose' => (getenv('CURL_VERBOSE') ? true : false),
];
