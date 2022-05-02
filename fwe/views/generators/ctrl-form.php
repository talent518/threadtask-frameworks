<?php
echo "<?php\n";
$modelObj = $model::create();
$priKeys = $model::priKeys();
?>
/**
 * 由<?=get_class($this)?>生成的代码
 *
 * @var $this \<?=$class?> 控制器
 * @var $model \<?=$model?> 数据行
 */
$errors = $model->getErrors();
<?="?>"?>
<div class="fields-form">
<?php foreach($modelObj->attributes as $attr => $_):if(!in_array($attr, $priKeys)):?>
	<div class="row">
		<label><?=$modelObj->getLabel($attr)?>：</label>
		<input name="<?=$attr?>" type="text" value="<?="<?=htmlspecialchars(\$model->$attr)?>"?>"/>
<?="<?php if(isset(\$errors['$attr'])):?>\n"?>
		<span class="error"><?="<?=implode(', ', (array) \$errors['$attr'])?>"?></span>
<?="<?php endif;?>\n"?>
	</div>
<?php endif;endforeach;?>
	<div class="row">
		<label></label>
		<button>保存</button>
	</div>
</div>
