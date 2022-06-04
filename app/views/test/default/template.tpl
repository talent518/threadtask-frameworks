<style type="text/css">
.template-rule .pre{white-space:pre;}
.template-rule pre{margin:0;}
.template-rule .html{border-top:1px #999 solid;border-bottom:1px #999 solid;padding:10px 0;}
.template-rule ol{list-style-type:none;margin:0;padding:0 0 0 1em;}
.template-rule dd > ol{padding:0;}
.template-rule p{margin:0;}
</style>
<div class="template-rule">

<?xml version="1.0" encoding="UTF-8" route="{$this:route}"?>

<dl>
	<dt>{keep}loop: $request{/keep}</dt>
{loop $request $key $val}
	<dd>
	{if is_array($val) && count($val)}
		<dl>
			<dt>$key</dt>
		{loop $val $k $v}
			<dd class="pre">{$k}: {$v|json}</dd>
		{/loop}
		</dl>
	{else}
		<pre>{$key}: {$val|json}</pre>
	{/if}
	</dd>
{/loop}
</dl>

<dl>
	<dt>{keep}loop: $get{/keep}</dt>
{loop $get $key $val}
	<dd class="pre">{$key}: {$val|json}</dd>
{/loop}
</dl>

<dl>
	<dt>{keep}loop: $headers{/keep}</dt>
{loop $headers $key $val}
	<dd class="pre">{$key}: {$val|json}</dd>
{/loop}
</dl>

<dl>
	<dt>{keep}loop: $get of keys{/keep}</dt>
{loop array_keys($get) $val}
	<dd>{$key}</dd>
{/loop}
</dl>

<dl>
	<dt>{keep}loop: array_keys($get){/keep}</dt>
{loop array_keys($get) $key}
	<dd>$key</dd>
{/loop}
</dl>

<dl>
	<dt>{keep}for $i = 0 to 10{/keep}</dt>
{for $i 0 10}
	<dd>{$i}</dd>
{/for}
</dl>

<dl>
	<dt>变量: 对象的属性</dt>
	<dd class="pre"><em>{keep}{$request:get|json}{/keep}</em> - {$request:get|json}</dd>
	<dd><em>{keep}{$request:method}{/keep}</em> - {$request:method}</dd>
	<dd><em>{keep}{$headers.Host}{/keep}</em> - {$headers.Host}</dd>
	<dd><em>{keep}{$request:headers.Host}{/keep}</em> - {$request:headers.Host}</dd>
</dl>

<dl>
	<dt>{keep}if($get.a > 0): ... elseif($get.a < 0): ... else: ... endif;{/keep}</dt>
{if isset($get.a)}
{if $get.a > 0}
	<dd>{keep}$get.a &gt; 0{/keep}</dd>
{elseif $get.a < 0}
	<dd>{keep}$get.a &lt; 0{/keep}</dd>
{else}
	<dd>{keep}$get.a = 0{/keep}</dd>
{/if}
{else}
	<dd>{keep}$get.a is null{/keep}</dd>
{/if}
</dl>

<dl>
	<dt>常量</dt>
	<dd><em>{keep}{ROOT}{/keep}</em> - {ROOT}</dd>
	<dd><em>{keep}{FWE_PATH}{/keep}</em> - {FWE_PATH}</dd>
	<dd><em>{keep}{@app/views}{/keep}</em> - {@app/views}</dd>
</dl>

<dl>
	<dt>keep嵌套</dt>
	<dd>{keep}$keep-{$keep}{/keep}</dd>
</dl>

{var $html = highlight_file(INFILE, true);}
<div class="html">{$html}</div>

<dl>
	<dt>{keep}{tpl subtpl}{/keep}</dt>
	<dd>{tpl subtpl}</dd>
</dl>

<dl>
	<dt>{keep}{tpl subtpl get hdrs=$headers post=$request:post i=1 b=false s='str'}{/keep}</dt>
	<dd>{tpl subtpl get hdrs=$headers post=$request:post i=1 b=false s='str'}</dd>
</dl>

<dl>
	<dt>{keep}{$func=func(array $vars, string $name)}{/keep}</dt>
	<dd>
{$func=func(array $vars, string $name)}
		<p>{$name}</p>
		<ol>
		{loop $vars $key $val}
			<li>{$key} {$val|html}</li>
		{/loop}
		</ol>
{/func}
	{php $func($headers, 'headers');}
	{php $func($get, 'get');}
	</dd>
</dl>

<dl>
	<dt>{keep}{$func=func($pid)use(&$tree, &$func)}{/keep}</dt>
	<dd>
{$func=func(int $pid)use(&$tree, &$func)}
	{if isset($tree[$pid])}
		<ol title="{php echo count($tree[$pid]);}">
		{loop $tree[$pid] $val}
			<li>
				<p>{$val.id} {$val.pid} {$val.name}</p>
				{php $func($val['id']);}
			</li>
		{/loop}
		</ol>
		{php unset($tree[$pid]);}
	{/if}
{/func}
	{php $func(0);}
	</dd>
</dl>
</div>
