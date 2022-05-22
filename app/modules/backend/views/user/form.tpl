{var $errors = $model->getErrors();}
<div class="fields-form">
	<div class="row">
		<label>用户名：</label>
		<input name="username" type="text" value="{$model:username}"/>
	{if isset($errors.username)}
		<span class="error">{$errors.username|join}</span>
	{/if}
	</div>
	<div class="row">
		<label>邮箱：</label>
		<input name="email" type="text" value="{$model:email}"/>
	{if isset($errors.email)}
		<span class="error">{$errors.email|join}</span>
	{/if}
	</div>
	<div class="row">
		<label>密码：</label>
		<input name="newpass" type="text" value="{$model:newpass}"/>
	{if isset($errors.newpass)}
		<span class="error">{$errors.newpass|join}</span>
	{/if}
	</div>
	<div class="row">
		<label></label>
		<button>保存</button>
	</div>
</div>
