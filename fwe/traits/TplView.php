<?php
namespace fwe\traits;

use fwe\utils\StringHelper;

trait TplView {
	private $_ireplace = 0, $_replaces = [];
	
	protected function getViewExtension() {
		return 'tpl';
	}
	
	protected function buildView(string $viewFile) {
		$rootDir = ROOT . '/';
		$rootLen = strlen($rootDir);
		if(!strncmp($viewFile, $rootDir, $rootLen)) {
			$phpFile = trim(preg_replace('/[^a-zA-Z0-9]+/', '-', substr($viewFile, $rootLen, -4)), '-');
		} else {
			$phpFile = md5($viewFile);
		}
		$phpFile = \Fwe::getAlias("@app/runtime/views/{$phpFile}.php");
		$phpPath = dirname($phpFile);
		if(!is_dir($phpPath)) mkdir($phpPath, 0755, true);
		
		if(!is_file($phpFile) || filemtime($phpFile) < filemtime($viewFile)) {
			$template = file_get_contents($viewFile);
			
			// 把PHP标记
			
			// PHP代码
			$template = preg_replace_callback('/\<\!\-\-\{php\s+(.+?)\s*\}\-\-\>/is', [$this, 'phpTags'], $template);
			$template = preg_replace_callback('/\{php\s+(.+?)\s*\}/is', [$this, 'phpTags'], $template);

			// 受保护的，不进行模板规则的影响
			$template = preg_replace_callback('/\{keep\}(.+?)\{\/keep\}/s', [$this, 'keepTags'], $template);
			$template = preg_replace_callback('/(\<\?|\?\>)/s', [$this, 'srcTags'], $template);
			
			// 控制语句
			$template = preg_replace_callback('/\{elseif\s+(.+?)\}/s', [$this, 'elseifTags'], $template);
			$template = preg_replace_callback('/\{else\}/s', [$this, 'elseTags'], $template);
			do {
				$count = 0;
				$template = preg_replace_callback('/\{for\s+(\S+)\s+(\S+)\s+(\S+)\}(.+?)\{\/for\}/s', [$this, 'forTags'], $template, -1, $count);
			} while($count > 0);
			do {
				$count = 0;
				$template = preg_replace_callback('/\{loop\s+(\S+)\s+(\S+)\}(.+?)\{\/loop\}/s', [$this, 'loopTags'], $template, -1, $count);
			} while($count > 0);
			do {
				$count = 0;
				$template = preg_replace_callback('/\{loop\s+(\S+)\s+(\S+)\s+(\S+)\}(.+?)\{\/loop\}/s', [$this, 'loopTags2'], $template, -1, $count);
			} while($count > 0);
			do {
				$count = 0;
				$template = preg_replace_callback('/\{if\s+(.+?)\}(.+?)\{\/if\}/s', [$this, 'ifTags'], $template, -1, $count);
			} while($count > 0);

			//变量
			$template = preg_replace_callback('/\{([a-zA-Z][a-zA-Z0-9_]+)\}/', [$this, 'echoTags'], $template);
			$template = preg_replace_callback('/\{([a-zA-Z][a-zA-Z0-9_]+)\|(\w+)\}/', [$this, 'formatTags'], $template);
			$template = preg_replace_callback('/\{(\$[a-zA-Z0-9_\.:]+)\}/', [$this, 'echoTags'], $template);
			$template = preg_replace_callback('/\{(\$[a-zA-Z0-9_\.:]+)\|(\w+)\}/', [$this, 'formatTags'], $template);
			$template = preg_replace_callback('/\{(\$[a-zA-Z0-9_\-\+\=\[\]]+\;)\}/', [$this, 'phpTags'], $template);
			$template = preg_replace_callback('/\{var\s+(\$.+?\;)\}/', [$this, 'phpTags'], $template);
			$template = preg_replace_callback('/\{tpl\s+(\S+)\}/', [$this, 'tplTags'], $template);
			$template = preg_replace_callback('/\{tpl\s+(\S+)\s+(.+?)\}/', [$this, 'tplTags'], $template);
			$template = preg_replace_callback('/(\$[a-zA-Z0-9_\.:]+)/', [$this, 'echoTags'], $template);
			$template = preg_replace_callback('/\{(@[a-zA-Z0-9\/-_\.]+)\}/', [$this, 'aliasTags'], $template);

			$template = strtr($template, $this->_replaces);
			
			$template = preg_replace('/\s+\?\>[\s]*\<\?php\s+/s', '', $template);
			
			file_put_contents($phpFile, $template);
			
			$this->_ireplace = 0;
			$this->_replaces = [];
		}
		
		return $phpFile;
	}
	
	private function makeKey() {
		$this->_ireplace ++;
		return "<!--TPL-TAG-{$this->_ireplace}-->";
	}
	
	public function srcTags(array $matches) {
		$matches[1] = var_export($matches[1], true);

		$key = $this->makeKey();
		$this->_replaces[$key] = "<?={$matches[1]}?>";
		return $key;
	}
	
	public function keepTags(array $matches) {
		$key = $this->makeKey();
		$this->_replaces[$key] = $matches[1];
		return $key;
	}
	
	private function makeVar($var){
		return preg_replace_callback('/\$[a-zA-Z0-9_\.:]+/', function($matches) {
			$vars = explode('.', $matches[0]);
			$var = array_shift($vars);
			$var = str_replace(':', '->', $var);
			foreach($vars as $v) {
				$vs = explode(':', $v);
				$v = array_shift($vs);
				$var .= (is_numeric($v) ? "[{$v}]" : "['{$v}']");
				if(count($vs) > 0)
					$var .= '->' . implode('->', $vs);
			}
			return $var;
		}, $var);
	}
	
	public function ifTags(array $matches) {
		$matches[1] = $this->makeVar($matches[1]);
		
		$key = $this->makeKey();
		$key2 = $this->makeKey();
		$this->_replaces[$key] = "<?php if({$matches[1]}): ?>";
		$this->_replaces[$key2] = "<?php endif; ?>";
		return $key . $matches[2] . $key2;
	}
	
	public function elseifTags(array $matches) {
		$matches[1] = $this->makeVar($matches[1]);
		
		$key = $this->makeKey();
		$this->_replaces[$key] = "<?php elseif({$matches[1]}): ?>";
		return $key;
	}
	
	public function elseTags(array $matches) {
		$key = $this->makeKey();
		$this->_replaces[$key] = "<?php else: ?>";
		return $key;
	}
	
	public function forTags(array $matches) {
		$matches[2] = $this->makeVar($matches[2]);
		$matches[3] = $this->makeVar($matches[3]);
		
		$key = $this->makeKey();
		$key2 = $this->makeKey();
		$this->_replaces[$key] = "<?php for({$matches[1]} = {$matches[2]}; {$matches[1]} < {$matches[3]}; {$matches[1]} ++): ?>";
		$this->_replaces[$key2] = '<?php endfor; ?>';
		return $key . $matches[4] . $key2;
	}
	
	public function loopTags(array $matches) {
		$matches[1] = $this->makeVar($matches[1]);
		
		$key = $this->makeKey();
		$key2 = $this->makeKey();
		$this->_replaces[$key] = "<?php foreach({$matches[1]} as {$matches[2]}): ?>";
		$this->_replaces[$key2] = '<?php endforeach; ?>';
		
		return $key . $matches[3] . $key2;
	}
	
	public function loopTags2(array $matches) {
		$matches[1] = $this->makeVar($matches[1]);

		$key = $this->makeKey();
		$key2 = $this->makeKey();
		$this->_replaces[$key] = "<?php foreach({$matches[1]} as {$matches[2]} => {$matches[3]}): ?>";
		$this->_replaces[$key2] = '<?php endforeach; ?>';
		
		return $key . $matches[4] . $key2;
	}
	
	public function echoTags(array $matches) {
		$matches[1] = $this->makeVar($matches[1]);
		
		$key = $this->makeKey();
		$this->_replaces[$key] = "<?={$matches[1]}?>";
		return $key;
	}
	
	public function formatTags(array $matches) {
		$matches[1] = $this->makeVar($matches[1]);
		
		$key = $this->makeKey();
		switch($matches[2]){
			case 'text'://html to text
				$this->_replaces[$key] = "<?=htmlspecialchars({$matches[1]})?>";
				break;
			case 'html'://text to html
				$this->_replaces[$key] = "<?=\$this->str2html({$matches[1]})?>";
				break;
			case 'json'://php var to json
				$this->_replaces[$key] = "<?=json_encode({$matches[1]}, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)?>";
				break;
			case 'money'://numeric to money
				$this->_replaces[$key] = "<?php printf('<b class=\"numeric\"><font style=\"font-family:Arial;\">¥</font>%.2f</b>', {$matches[1]})?>";
				break;
			default:
				$this->_replaces[$key] = "<?={$matches[1]} /* {$matches[2]} */?>";
				break;
		}
		return $key;
	}
	
	public function phpTags(array $matches) {
		$key = $this->makeKey();
		$this->_replaces[$key] = "<?php {$matches[1]} ?>";
		return $key;
	}
	
	public function tplTags(array $matches) {
		$matches[1] = $this->makeVar($matches[1]);
		
		if(isset($matches[2])) {
			$params = [];
			foreach(preg_split('/\s+/', $matches[2], -1, PREG_SPLIT_NO_EMPTY) as $param) {
				@list($key, $value) = explode('=', $param, 2);
				if($value === null) {
					$params[] = "'$key' => \$$key";
				} else {
					$value = $this->makeVar($value);
					$params[] = "'$key' => $value";
				}
			}
			$params = implode(', ', $params);
		} else {
			$params = null;
		}
		
		$key = $this->makeKey();
		$this->_replaces[$key] = "<?=\$this->renderView(\"{$matches[1]}\", [{$params}])?>";
		return $key;
	}
	
	public function aliasTags(array $matches) {
		$key = $this->makeKey();
		$this->_replaces[$key] = "<?=\Fwe::getAlias('{$matches[1]}')?>";
		return $key;
	}
	
	protected function str2html($string) {
		return StringHelper::str2html($string);
	}
}
