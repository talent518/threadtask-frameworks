<?php
echo "<?php\n";
?>
/**
 * 由<?=get_class($this)?>生成的代码
 *
 * @var $this \<?=$class?> 控制器
 * @var $model \<?=$model?> 数据行
 */
if(isset($data->error)) {
	echo "<pre>{$data->error}</pre>";
	return;
}
<?="?>"?>

<form class="update-form" action="/<?="<?=\$this->route?>"?>create?backUrl=<?="<?=urlencode(\$backUrl)?>"?>" method="post">
	<h1><a class="list" href="<?="<?=\$backUrl ?: '/' . trim(\$this->route, '/')?>"?>"><?=substr($className, 0, -10)?></a> &gt; <a href="/<?="<?=\$this->route?>"?>create?backUrl=<?="<?=urlencode(\$backUrl)?>"?>">添加</a></h1>
	<?="<?=\$this->renderFile(\$this->getViewFile('form'), ['model'=>\$model])?>\n"?>
</form>
