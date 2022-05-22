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
 * @var $generator \fwe\db\Generator 生成器对象
 */

?>
/**
 * 由<?=get_class($this)?>生成的代码
 *
 * @var \<?=$class?> $this 控制器
 * @var \<?=$model?> $model 数据行
 * @var string $backUrl 返回URL地址
 * @var \stdClass $data 返回URL地址
 */
if(isset($data->error)) {
	echo "<pre>{$data->error}</pre>";
	return;
}
<?="?>"?>

<form class="update-form" action="/<?="<?=\$this->route?>"?>create?backUrl=<?="<?=urlencode(\$backUrl)?>"?>" method="post">
	<h1><a class="list" href="<?="<?=\$backUrl ?: '/' . \$this->route?>"?>"><?=substr($className, 0, -10)?></a> &gt; <a href="/<?="<?=\$this->route?>"?>create?backUrl=<?="<?=urlencode(\$backUrl)?>"?>">添加</a></h1>
	<?="<?=\$this->renderView('form', ['model'=>\$model])?>\n"?>
</form>
