{if isset($data->error)}
	<pre>{$data->error}</pre>
{else}
	<form class="update-form" action="/{$this:route}update?id={$model:cno}&backUrl={$backUrl|url}" method="post">
		<h1><a class="list" href="{if $backUrl}{$backUrl}{else}/{$this:route}{/if}">分类列表</a> &gt; <a href="/{$this:route}update?id={$model:cno}&backUrl={$backUrl|url}">编辑: {$model:cno}</a></h1>
		{tpl form model}
	</form>
{/if}
