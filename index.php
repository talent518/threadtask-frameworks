<?php
defined('ROOT') or define('ROOT', __DIR__);
defined('INFILE') or define('INFILE', __FILE__);
defined('APP_PATH') or define('APP_PATH', ROOT . '/app');

include_once ROOT . '/fwe/Fwe.php';
Fwe::setAlias('@app', APP_PATH);

Fwe::boot();
