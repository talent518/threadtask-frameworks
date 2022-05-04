<?php
echo "<?php\n";

/**
 * @var $this \fwe\base\Controller 控制器对象
 * @var $model string 数据模块类全名
 * @var $search string 搜索模块类全名
 * @var $class string 控制器类全名
 * @var $base string 控制器父类全名
 * @var $namespace string 控制器命名空间
 * @var $className string 控制器类名
 * @var $isJson bool 是否作为JSON响应
 * @var $isRestful bool 是否作为RESTful规范
 * @var $_ mixed 忽略
 * @var $generator \fwe\db\Generator 生成器对象
 */

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
