<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>{keep}<?=\Fwe::$app->name?> - 管理端{/keep} - {THREAD_TASK_NAME}</title>
<link rel="stylesheet" type="text/css" href="/backend/static/backend.css"/>
<script type="text/javascript" src="/backend/static/jquery.min.js"></script>
<script type="text/javascript" src="/backend/static/jquery-ui_draggable.js"></script>
<script type="text/javascript" src="/backend/static/jquery-mousewheel.js"></script>
</head>
<body>
{if $user}
<div class="layout-wrapper">
	<div class="info">欢迎，<b>{$user:username}</b>(<em>{$user:email}</em>) - <a href="/backend/default/logout?backUrl={$uri|url}">登出</a></div>
	<a href="/backend" class="index" title="管理首页">管理端</a>
	<div id="j-layout-nav" class="nav">
		<a href="/backend/user"{if $this:id === 'user'} class="active"{/if}>用户管理</a>
	</div>
	<div class="content">{$content}</div>
</div>
<script type="text/javascript">
(function($) {
	const navElem = $('#j-layout-nav').mousewheel(function(e,delta) {
		navElem.scrollTop(navElem.scrollTop() - delta * 200);
	});
})(jQuery);
</script>
{else}
{$content}
{/if}
</body>
</html>