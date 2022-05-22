<form class="search-form" action="?" method="get">
<h1>
	<a class="list" href="/{$this:route}">用户列表</a>
</h1>
<table class="data-grid">
	<thead>
		<tr>
			<th class="order{if $model:orderBy === 'uid'}{if $model:isDesc} desc{else} asc{/if}{/if}" field="uid">用户ID</th>
			<th class="order{if $model:orderBy === 'username'}{if $model:isDesc} desc{else} asc{/if}{/if}" field="username">用户名</th>
			<th class="order{if $model:orderBy === 'email'}{if $model:isDesc} desc{else} asc{/if}{/if}" field="email">邮箱</th>
			<th class="order{if $model:orderBy === 'registerTime'}{if $model:isDesc} desc{else} asc{/if}{/if}" field="registerTime">注册时间</th>
			<th class="order{if $model:orderBy === 'loginTime'}{if $model:isDesc} desc{else} asc{/if}{/if}" field="loginTime">最后登录时间</th>
			<th class="order{if $model:orderBy === 'loginTimes'}{if $model:isDesc} desc{else} asc{/if}{/if}" field="loginTimes">登录次数</th>
			<th class="oper">操作</th>
		</tr>
		<tr class="search">
			<th><input name="uid" type="text" value="{$model:uid|text}" /></th>
			<th><input name="username" type="text" value="{$model:username|text}" /></th>
			<th><input name="email" type="text" value="{$model:email|text}" /></th>
			<th><input name="registerTime" type="text" value="{$model:registerTime|text}" /></th>
			<th><input name="loginTime" type="text" value="{$model:loginTime|text}" /></th>
			<th><input name="loginTimes" type="text" value="{$model:loginTimes|text}" /></th>
			<th class="oper"><a class="create" href="/{$this:route}create">添加</a></th>
		</tr>
	</thead>
	<tbody>
{loop $model:result $row}
		<tr>
			<td><div class="html">{$row:uid|html}</div></td>
			<td><div class="html">{$row:username|html}</div></td>
			<td><div class="html">{$row:email|html}</div></td>
			<td><div class="html">{$row:registerTime|html}</div></td>
			<td><div class="html">{$row:loginTime|html}</div></td>
			<td><div class="html">{$row:loginTimes|html}</div></td>
			<td class="oper">
				<a class="view" href="/{$this:route}view?id={$row:uid}">查看</a>
				<a class="update" href="/{$this:route}update?id={$row:uid}">编辑</a>
				<a class="delete" href="/{$this:route}delete?id={$row:uid}">删除</a>
			</td>
		</tr>
{/loop}
	</tbody>
</table>
<div class="multi-page">
{if $model:pages > 1}
{var $min = max(1, $model->page - 5);$max = min($model->pages, $min + 10);}
	<div class="multi">
	{for $i $min $max}
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
