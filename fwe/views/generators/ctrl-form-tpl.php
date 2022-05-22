<?php
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
{var $errors = $model->getErrors();}
<div class="fields-form">
<?php foreach($modelObj->attributes as $attr => $_):if(!in_array($attr, $priKeys)):?>
	<div class="row">
		<label><?=$modelObj->getLabel($attr)?>：</label>
		<input name="<?=$attr?>" type="text" value="{$model:<?=$attr?>}"/>
{if isset($errors.<?=$attr?>)}
		<span class="error">{$errors.<?=$attr?>|join}</span>
{/if}
	</div>
<?php endif;endforeach;?>
	<div class="row">
		<label></label>
		<button>保存</button>
	</div>
</div>
