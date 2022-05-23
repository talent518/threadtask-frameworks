<form class="search-form" action="?" method="get">
<h1>
	<a class="list" href="/{$this:route}">分类列表</a>
</h1>
<table class="data-grid">
	<thead>
		<tr>
			<th class="order{if $model:orderBy === 'cno'}{if $model:isDesc} desc{else} asc{/if}{/if}" field="cno">分类ID</th>
			<th class="order{if $model:orderBy === 'cname'}{if $model:isDesc} desc{else} asc{/if}{/if}" field="cname">分类名</th>
			<th class="order{if $model:orderBy === 'cdesc'}{if $model:isDesc} desc{else} asc{/if}{/if}" field="cdesc">描述</th>
			<th class="oper">操作</th>
		</tr>
		<tr class="search">
			<th><input name="cno" type="text" value="{$model:cno|text}" /></th>
			<th><input name="cname" type="text" value="{$model:cname|text}" /></th>
			<th><input name="cdesc" type="text" value="{$model:cdesc|text}" /></th>
			<th class="oper"><a class="create" href="/{$this:route}create">添加</a></th>
		</tr>
	</thead>
	<tbody>
{loop $model:result $row}
		<tr>
			<td><div class="html">{$row:cno|html}</div></td>
			<td><div class="html">{$row:cname|html}</div></td>
			<td><div class="html">{$row:cdesc|html}</div></td>
			<td class="oper">
				<a class="view" href="/{$this:route}view?id={$row:cno}">查看</a>
				<a class="update" href="/{$this:route}update?id={$row:cno}">编辑</a>
				<a class="delete" href="/{$this:route}delete?id={$row:cno}">删除</a>
			</td>
		</tr>
{/loop}
	</tbody>
</table>
<div class="multi-page">
{if $model:pages > 1}
{var $min = max(1, $model->page - 5);$max = min($model->pages, $min + 10); $min = max(1, $max - 10);}
	<div class="multi">
	{for $i $min $max+1}
		<span{if $i==$model:page} class="cur"{/if}>{$i}</span>
	{/for}
	</div>
{/if}
	<label class="info">总共 {$model:total} 行，{$model:pages} 页</label>
	<label class="page"><span>当前页：</span><input name="page" type="text" value="{$model:page}"/></label>
	<label class="size"><span>每页行数：</span><input name="size" type="text" value="{$model:size}"/></label>
	<input name="orderBy" type="hidden" value="{$model:orderBy}"/>
	<input name="isDesc" type="hidden" value="{$model:isDesc}"/>
	<button class="search" type="submit">GO</button>
</div>
</form>
{if $model:pages > 1}
<script type="text/javascript">
(function($) {
	const formElem = $('.search-form');
	$('.multi-page > .multi > span', formElem).click(function() {
		$('.multi-page > .page > input[name=page]', formElem).val($(this).text());
		formElem.submit();
	});
})(jQuery);
</script>
{/if}
<script type="text/javascript">
(function($) {
	const formElem = $('.search-form');
	$('th.order', formElem).click(function() {
		$('.multi-page > input[name=orderBy]', formElem).val($(this).attr('field'));
		$('.multi-page > input[name=isDesc]', formElem).val($(this).is('.asc') ? 1 : 0);
		formElem.submit();
	});
	$('a.create, a.view, a.update, a.delete', formElem).click(function() {
		if($(this).is('.delete') && !confirm('你确定要删除该记录吗？')) {
			return false;
		}

		const backUrl = encodeURIComponent(location.pathname + '?' + formElem.serialize());
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
