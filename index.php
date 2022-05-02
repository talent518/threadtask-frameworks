<?php
if(is_main_task()) {
	$isNoneExt = false;
	foreach(['threadtask', 'event', 'curl', 'mysqli', 'sockets', 'pcntl', 'posix', 'date'] as $ext) {
		if(!extension_loaded($ext)) {
			$isNoneExt = true;
			echo "extension $ext not exists\n";
		}
	}
	if($isNoneExt) return;
}

defined('ROOT') or define('ROOT', __DIR__);
defined('INFILE') or define('INFILE', __FILE__);
defined('APP_PATH') or define('APP_PATH', ROOT . '/app');

include_once ROOT . '/fwe/Fwe.php';
Fwe::setAlias('@app', APP_PATH);

$ret = Fwe::boot();

if(is_scalar($ret)) {
	is_bool($ret) or die($ret);
} else if($ret !== null) {
	var_export($ret);
}
