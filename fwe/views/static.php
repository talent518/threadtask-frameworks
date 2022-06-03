<?php
/**
 * @var fwe\web\StaticController $this
 * @var string $file 当前路径
 * @var string $key 要排序的列名
 * @var string $sort 排序方式: asc, desc
 * @var array $files 目录名或文件名列表
 */
?><!DOCTYPE html>
<html lang="zh-cn">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="referrer" content="origin" />
    <meta http-equiv="Cache-Control" content="no-transform" />
    <meta http-equiv="Cache-Control" content="no-siteapp" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>/<?php echo $this->route, $file;?></title>
    <style type="text/css">
    body{margin:0;padding:5px;}
    table{border:1px #ccc solid;border-width:1px 0 0 1px;border-spacing:0;margin:0 auto;}
    th,td{border:1px #ccc solid;padding:5px;}
    td{border-width:0 1px 1px 0;}
    th{border-width:0 1px 2px 0;}
    </style>
</head>
<body>
<table>
	<thead>
		<tr><?php
		$titles = [
			'name' => 'Name',
			'size' => 'Size',
			'type' => 'Type',
			'perms' => 'Perm',
			'atime' => 'Time of last access',
			'mtime' => 'Time of last modification',
			'ctime' => 'Time of last modification',
		];
		foreach($titles as $k=>$t):
			if($k === $key):
				?><th><a href="?key=<?=$k?><?=($sort === 'asc' ? '&sort=desc' : null)?>"><?=$t?><?=($sort === 'asc' ? '⇈' : '⇊')?></a></th><?php
			else:
				?><th><a href="?key=<?=$k?>"><?=$t?></a></th><?php
			endif;
		endforeach;
		?></tr>
	</thead>
	<tbody><?php
	if($file !== ''):
		?><tr><td colspan="7"><a href="<?=strrpos($file, '/') ? '..' : '/'?>">..</a></td></tr><?php
	endif;
	foreach($files as $file):
		?><tr>
			<td><a href="<?=$file['url']?>"><?=$file['name']?></a></td>
			<td><?=$file['size']?></td>
			<td><?=$file['type']?></td>
			<td><?=$file['perms']?></td>
			<td><?=$file['atime']?date('Y-m-d H:i:s', $file['atime']):'-'?></td>
			<td><?=$file['mtime']?date('Y-m-d H:i:s', $file['mtime']):'-'?></td>
			<td><?=$file['ctime']?date('Y-m-d H:i:s', $file['ctime']):'-'?></td>
		</tr><?php
	endforeach;
	?></tbody>
</table>
</body>
</html>