<?php
/**
 * @var $this \fwe\base\Controller 控制器对象
 * @var $model string 数据模块类全名
 * @var $search string 搜索模块类全名
 * @var $class string 控制器类全名
 * @var $base string 控制器父类全名
 * @var $namespace string 控制器命名空间
 * @var $className string 控制器类名
 * @var $title string 标题
 * @var $isJson bool 是否作为JSON响应
 * @var $isRestful bool 是否作为RESTful规范
 * @var $_ mixed 忽略
 * @var $generator \fwe\db\Generator 生成器对象
 */

$modelObj = $model::create();
?>
<div class="view-details">
	<h1><a class="list" href="{if $backUrl}{$backUrl}{else}/{$this:route}{/if}"><?=$title?></a> &gt; <a href="/{$this:route}view?<?=$generator->genKeyForModel($model, 'model', false, ':')?>&backUrl={$backUrl|url}">查看: {$model:<?=implode('} - {$model:', $model::priKeys())?>}</a></h1>
	<table>
	<?php foreach($modelObj->attributes as $attr => $_):?>
		<tr><th><?=$modelObj->getLabel($attr)?></th><td><div class="html">{$model:<?=$attr?>|html}</div></td></tr>
	<?php endforeach;?>
	</table>
</div>
