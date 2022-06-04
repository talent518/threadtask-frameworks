<?php
namespace fwe\traits;

use fwe\utils\FileHelper;
use fwe\utils\StringHelper;

trait TplView {
	protected $replaceWhiteSpace = null;
	private $_ireplace = 0, $_replaces = [];
	
	protected function getViewExtension() {
		return 'tpl';
	}
	
	protected function getViewPhpFile(string $view) {
		if(!strncmp($view, '@', 1)) {
			$file = \Fwe::getAlias('@app/runtime/views/' . substr($view, 1));
		} elseif(!strncmp($view, '//', 2)) {
			$file = \Fwe::getAlias('@app/runtime/views/' . ltrim($view, '/'));
		} elseif(!strncmp($view, '/', 1)) {
			$file = \Fwe::getAlias('@app/runtime/views/' . $this->module->getRoute() . ltrim($view, '/'));
		} else {
			$file = \Fwe::getAlias('@app/runtime/views/' . $this->getRoute() . ltrim($view, '/'));
		}
		
		$ext = pathinfo($file, PATHINFO_EXTENSION);
		if($ext) {
			$file = substr($file, 0, - strlen($ext) - 1);
		}
		
		return "$file.php";
	}
	
	protected function buildView(string $viewFile, string $view) {
		$phpFile = $this->getViewPhpFile($view);
		
		if(!is_file($phpFile) || filemtime($phpFile) < filemtime($viewFile)) {
			$template = file_get_contents($viewFile);
			
			// PHP代码
			$template = preg_replace_callback('/\<\!\-\-\{php\s+(.+?)\s*\}\-\-\>/is', [$this, 'phpTags'], $template);
			$template = preg_replace_callback('/\{php\s+(.+?)\s*\}/is', [$this, 'phpTags'], $template);

			// 受保护的，不进行模板规则的影响
			$template = preg_replace_callback('/\{keep\}(.+?)\{\/keep\}/s', [$this, 'keepTags'], $template);
			$template = preg_replace_callback($this->replaceWhiteSpace === null ? '/(\<\?\s*|\?\>\s*)/s' : '/(\<\?|\?\>)/s', [$this, 'srcTags'], $template);
			
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
			
			// 匿名函数
			$template = preg_replace_callback('/\{(\$[a-zA-Z0-9_]+)\s*=\s*func\s*\(([^\)]+)\)(\s*use\s*\([^\)]+\))?\}(.+?)\{\/func\}/s', [$this, 'funcTags'], $template);
			
			// 变量
			$template = preg_replace_callback('/\{([a-zA-Z][a-zA-Z0-9_]+)\}/', [$this, 'echoTags'], $template);
			$template = preg_replace_callback('/\{([a-zA-Z][a-zA-Z0-9_]+)\|(\w+)\}/', [$this, 'formatTags'], $template);
			$template = preg_replace_callback('/\{(\$[a-zA-Z0-9_\.:]+)\}/', [$this, 'echoTags'], $template);
			$template = preg_replace_callback('/\{(\$[a-zA-Z0-9_\.:]+)\|(\w+)\}/', [$this, 'formatTags'], $template);
			$template = preg_replace_callback('/\{(\$[a-zA-Z0-9_\-\+\=\[\]]+\;)\}/', [$this, 'phpTags'], $template);
			$template = preg_replace_callback('/\{var\s+(\$.+?\;)\}/', [$this, 'phpTags'], $template);
			$template = preg_replace_callback('/\{tpl\s+(\S+)\}/', [$this, 'tplTags'], $template);
			$template = preg_replace_callback('/\{tpl\s+(\S+)\s+(.+?)\}/', [$this, 'tplTags'], $template);
			$template = preg_replace_callback('/(\$[a-zA-Z_][a-zA-Z0-9_\.:]*)/', [$this, 'echoTags'], $template);
			$template = preg_replace_callback('/\{(@[a-zA-Z0-9\/-_\.]+)\}/', [$this, 'aliasTags'], $template);
			
			//清除空白字符
			if($this->replaceWhiteSpace !== null){
				$template = preg_replace('/\s*[\r\n]+\s*/s', $this->replaceWhiteSpace, $template);
			}

			$template = strtr($template, $this->_replaces);
			
			FileHelper::mkdir(dirname($phpFile)) and file_put_contents($phpFile, $template);
			
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
		return preg_replace_callback('/\$[a-zA-Z_][a-zA-Z0-9_\.:]+/', function($matches) {
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
	
	public function funcTags(array $matches) {
		$key = $this->makeKey();
		$key2 = $this->makeKey();
		$this->_replaces[$key] = "<?php {$matches[1]} = function({$matches[2]}){$matches[3]} {?>";
		$this->_replaces[$key2] = '<?php }?>';
		
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
		switch($matches[2]) {
			case 'gmt':// date GMT
				$this->_replaces[$key] = "<?=date('D, d F Y H:i:s', {$matches[1]})?> GMT";
				break;
			case 'date':// date
				$this->_replaces[$key] = "<?=date('Y-m-d H:i:s', {$matches[1]})?> GMT";
				break;
			case 'url'://urlencode
				$this->_replaces[$key] = "<?=urlencode({$matches[1]})?>";
				break;
			case 'join':// join array
				$this->_replaces[$key] = "<?=implode(', ', (array) {$matches[1]})?>";
				break;
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
				$this->_replaces[$key] = "<?php printf('<b class=\"numeric\"><font style=\"font-family:Arial;\">¥</font>%.2f</b>', {$matches[1]});?>";
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
