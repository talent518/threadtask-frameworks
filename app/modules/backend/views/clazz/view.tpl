<div class="view-details">
	<h1><a class="list" href="{if $backUrl}{$backUrl}{else}/{$this:route}{/if}">分类列表</a> &gt; <a href="/{$this:route}view?id={$model:cno}&backUrl={$backUrl|url}">查看: {$model:cno}</a></h1>
	<table>
			<tr><th>分类ID</th><td><div class="html">{$model:cno|html}</div></td></tr>
			<tr><th>分类名</th><td><div class="html">{$model:cname|html}</div></td></tr>
			<tr><th>描述</th><td><div class="html">{$model:cdesc|html}</div></td></tr>
		</table>
</div>
