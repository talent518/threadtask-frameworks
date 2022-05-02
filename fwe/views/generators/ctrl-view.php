<?php
echo "<?php\n";
$modelObj = $model::create();
?>
use fwe\utils\StringHelper;

/**
 * @var $this \<?=$class?> 控制器
 * @var $model \<?=$model?> 数据行
 */
<?="?>"?>
<div class="view-details">
	<h1><a class="list" href="/<?="<?=trim(\$this->route, '/')?>"?>"><?=substr($className, 0, -10)?></a> &gt; <a href="/<?="<?=\$this->route?>"?>view?<?=$generator->genKeyForModel($model, 'model')?>">查看: <?='<?="{$model->'.implode('} - {\$model->', $model::priKeys()).'}"?>'?></a></h1>
<table>
<?php foreach($modelObj->attributes as $attr => $_):?>
	<tr><th><?=$modelObj->getLabel($attr)?></th><td><div class="html"><?="<?=StringHelper::str2html(\$model->$attr)?>"?></div></td></tr>
<?php endforeach;?>
</table>
</div>
