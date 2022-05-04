<?php
echo "<?php\n";
$modelObj = $model::create();
$searchKeys = $model::searchKeys();
?>
use fwe\utils\StringHelper;

/**
 * 由<?=get_class($this)?>生成的代码
 *
 * @var $this \<?=$class?> 控制器
 * @var $model \<?=$search?> 搜索模块
 * @var $row \<?=$model?> 数据行
 */
<?="?>"?>
<form class="search-form" action="?" method="get">
<h1>
	<a class="right create" href="/<?="<?=\$this->route?>"?>create">添加</a>
	<a class="list" href="/<?="<?=trim(\$this->route, '/')?>"?>"><?=substr($className, 0, -10)?></a>
</h1>
<table class="data-grid">
	<thead>
		<tr>
<?php foreach($searchKeys as $attr => $_):?>
			<th class="order<?="<?=\$model->orderBy === '$attr' ? (\$model->isDesc ? ' desc' : ' asc') : null?>"?>" field="<?=$attr?>"><?=$modelObj->getLabel($attr)?></th>
<?php endforeach;?>
			<th class="oper">操作</th>
		</tr>
		<tr class="search">
<?php foreach($searchKeys as $attr => $_):?>
			<th><input name="<?=$attr?>" type="text" value="<?="<?=\$model->$attr?>"?>" /></th>
<?php endforeach;?>
			<th class="oper">&nbsp;</th>
		</tr>
	</thead>
	<tbody>
<?php echo "<?php foreach(\$model->result as \$row):?>\n";?>
		<tr>
<?php foreach($searchKeys as $attr => $_):?>
			<td><div class="html"><?="<?=StringHelper::str2html(\$row->$attr)?>"?></div></td>
<?php endforeach;?>
			<td class="oper">
				<a class="view" href="/<?="<?=\$this->route?>"?>view?<?=$generator->genKeyForModel($model, 'row')?>">查看</a>
				<a class="update" href="/<?="<?=\$this->route?>"?>update?<?=$generator->genKeyForModel($model, 'row')?>">编辑</a>
				<a class="delete" href="/<?="<?=\$this->route?>"?>delete?<?=$generator->genKeyForModel($model, 'row')?>">删除</a>
			</td>
		</tr>
<?php echo "<?php endforeach;?>\n";?>
	</tbody>
</table>
<div class="multi-page">
<?="<?php if(\$model->pages > 1):\$min = max(1, \$model->page - 5);\$max = min(\$model->pages, \$min + 10);?>\n"?>
	<div class="multi">
<?="	<?php for(\$i=\$min; \$i<=\$max; \$i++):?>\n"?>
		<span<?="<?=\$i==\$model->page?' class=\"cur\"':null?>"?>><?="<?=\$i?>"?></span>
<?="	<?php endfor;?>\n"?>
	</div>
<?="<?php endif;?>\n"?>
	<label class="info">总共 <?="<?=\$model->total?>"?> 行，<?="<?=\$model->pages?>"?> 页</label>
	<label class="page"><span>当前页：</span><input name="page" type="text" value="<?="<?=\$model->page?>"?>"/></label>
	<label class="size"><span>每页行数：</span><input name="size" type="text" value="<?="<?=\$model->size?>"?>"/></label>
	<input name="orderBy" type="hidden" value="<?="<?=\$model->orderBy?>"?>"/>
	<input name="isDesc" type="hidden" value="<?="<?=\$model->isDesc?>"?>"/>
	<button class="search" type="submit">GO</button>
</div>
</form>
<?="<?php if(\$model->pages > 1):?>"?>
<script type="text/javascript">
(function($) {
	const $form = $('.search-form');
	$('.multi-page > .multi > span', $form).click(function() {
		$('.multi-page > .page > input[name=page]', $form).val($(this).text());
		$form.submit();
	});
})(jQuery);
</script>
<?="<?php endif;?>\n"?>
<script type="text/javascript">
(function($) {
	const $form = $('.search-form');
	$('th.order', $form).click(function() {
		$('.multi-page > input[name=orderBy]', $form).val($(this).attr('field'));
		$('.multi-page > input[name=isDesc]', $form).val($(this).is('.asc') ? 1 : 0);
		$form.submit();
	});
	$('a.create, a.view, a.update, a.delete', $form).click(function() {
		if($(this).is('.delete') && !confirm('你确定要删除该记录吗？')) {
			return false;
		}

		const backUrl = encodeURIComponent(location.pathname + '?' + $form.serialize());
		let url = this.href;
		if(url.indexOf('?') == -1) {
			url += '?backUrl=' + backUrl;
		} else {
			url += '&backUrl=' + backUrl;
		}
		location.href = url;
		return false;
	});
})(jQuery);
</script>
