{var $errors = $model->getErrors();}
<div class="fields-form">
	<div class="row">
		<label>分类名：</label>
		<input name="cname" type="text" value="{$model:cname}"/>
	{if isset($errors.cname)}
		<span class="error">{$errors.cname|join}</span>
	{/if}
	</div>
	<div class="row">
		<label>描述：</label>
		<input name="cdesc" type="text" value="{$model:cdesc}"/>
	{if isset($errors.cdesc)}
		<span class="error">{$errors.cdesc|join}</span>
	{/if}
	</div>
	<div class="row">
		<label></label>
		<button>保存</button>
	</div>
</div>
