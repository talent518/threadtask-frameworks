{loop $this:module:controllerObjects $ctrl}
<p><a href="/{$ctrl:route}">{php echo get_class($ctrl);}</a></p>
{/loop}

<p><a href="/{$this:route}template?a={php echo rand(0, 6) - 3;}&b={php echo rand();}">Template</a></p>
<pre>{$__params__|json}</pre>
