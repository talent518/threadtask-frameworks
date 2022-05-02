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

<form class="update-form" action="/<?="<?=\$this->route?>"?>update?<?=$generator->genKeyForModel($model, 'model')?>" method="post">
	<h1><a class="list" href="/<?="<?=trim(\$this->route, '/')?>"?>"><?=substr($className, 0, -10)?></a> &gt; <a href="/<?="<?=\$this->route?>"?>update?<?=$generator->genKeyForModel($model, 'model')?>">编辑: <?='<?="{$model->'.implode('} - {\$model->', $model::priKeys()).'}"?>'?></a></h1>
	<?="<?=\$this->renderFile(\$this->getViewFile('form'), ['model'=>\$model])?>\n"?>
</form>
