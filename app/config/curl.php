<?php
return [
	'class' => 'fwe\curl\Application',
	'id' => 'fwe',
	'name' => 'FWE框架',
	'verbose' => (getenv("CURL_VERBOSE") ? true : false),
];
