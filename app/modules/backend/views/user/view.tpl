<div class="view-details">
	<h1><a class="list" href="{if $backUrl}{$backUrl}{else}/{$this:route}{/if}">用户列表</a> &gt; <a href="/{$this:route}view?id={$model:uid}&backUrl={$backUrl|url}">查看: {$model:uid}</a></h1>
	<table>
			<tr><th>用户ID</th><td><div class="html">{$model:uid|html}</div></td></tr>
			<tr><th>用户名</th><td><div class="html">{$model:username|html}</div></td></tr>
			<tr><th>邮箱</th><td><div class="html">{$model:email|html}</div></td></tr>
			<tr><th>注册时间</th><td><div class="html">{$model:registerTime|html}</div></td></tr>
			<tr><th>最后登录时间</th><td><div class="html">{$model:loginTime|html}</div></td></tr>
			<tr><th>登录次数</th><td><div class="html">{$model:loginTimes|html}</div></td></tr>
		</table>
</div>
