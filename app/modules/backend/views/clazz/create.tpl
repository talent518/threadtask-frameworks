{if isset($data->error)}
	<pre>{$data->error}</pre>
{else}
	<form class="update-form" action="/{$this:route}create?backUrl={$backUrl|url}" method="post">
		<h1><a class="list" href="{if $backUrl}{$backUrl}{else}/{$this:route}{/if}">分类列表</a> &gt; <a href="/{$this:route}create?backUrl={$backUrl|url}">添加</a></h1>
		{tpl form model}
	</form>
{/if}
