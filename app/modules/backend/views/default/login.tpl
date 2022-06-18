{var $errors = $model->getErrors();}
<div class="login-form">
<form class="fields-form s-{$model:scene}" action="?{if $model:scene === 'email'}isEmail=1{/if}backUrl={$backUrl|url}" method="post">
	<h1>管理端登录</h1>
{if $model:scene === 'username'}
	<div class="row">
		<label>用户名：</label>
		<input name="username" type="text" value="{$model:username}"/>
	{if isset($errors.username)}
		<span class="error">{$errors.username|join}</span>
	{/if}
	</div>
{else}
	<div class="row">
		<label>邮箱：</label>
		<input name="email" type="text" value="{$model:email}"/>
	{if isset($errors.email)}
		<span class="error">{$errors.email|join}</span>
	{/if}
	</div>
{/if}
	<div class="row">
		<label>密码：</label>
		<input name="password" type="password" value="{$model:password}"/>
	{if isset($errors.password)}
		<span class="error">{$errors.password|join}</span>
	{/if}
	</div>
	<div class="row">
		<label></label>
		<button>立即登录</button>
	{if isset($errors.error)}
		<span class="error">{$errors.error|join}</span>
	{/if}
	</div>
	<div class="row">
		<label></label>
	{if $model:scene === 'username'}
		<a href="?isEmail=1&backUrl={$backUrl|url}">使用邮箱登录</a>
	{else}
		<a href="?backUrl={$backUrl|url}">使用用户名登录</a>
	{/if}
	</div>
</form>
</div>