<?php
/**
 * @var string $content 子界面内容: HTML
 */
?><!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title><?=\Fwe::$app->name?> - <?=THREAD_TASK_NAME?></title>
<link rel="stylesheet" type="text/css" href="/static/main.css"/>
<script type="text/javascript" src="/static/jquery.min.js"></script>
</head>
<body>
<?=$content?>
</body>
</html>