<?php
namespace fwe\console;

class Controller extends \fwe\base\Controller {

	// 文字色控制
	const FG_BLACK = 30;
	const FG_RED = 31;
	const FG_GREEN = 32;
	const FG_YELLOW = 33;
	const FG_BLUE = 34;
	const FG_PURPLE = 35;
	const FG_CYAN = 36;
	const FG_GREY = 37;

	// 背景色控制
	const BG_BLACK = 40;
	const BG_RED = 41;
	const BG_GREEN = 42;
	const BG_YELLOW = 43;
	const BG_BLUE = 44;
	const BG_PURPLE = 45;
	const BG_CYAN = 46;
	const BG_GREY = 47;

	// 字符样式控制
	const RESET = 0;
	const NORMAL = 0;
	const BOLD = 1;
	const ITALIC = 3;
	const UNDERLINE = 4;
	const BLINK = 5;
	const NEGATIVE = 7;
	const CONCEALED = 8;
	const CROSSED_OUT = 9;
	const FRAMED = 51;
	const ENCIRCLED = 52;
	const OVERLINED = 53;

	/**
	 *
	 * @var bool
	 */
	public $isColor;

	public function init() {
		parent::init();
		$this->isColor = DIRECTORY_SEPARATOR === '\\' ? getenv('ANSICON') !== false || getenv('ConEmuANSI') === 'ON' : function_exists('posix_isatty') && @posix_isatty(STDOUT);
	}
	
	/**
	 * @param mixed $string
	 * @param int ...$colors
	 */
	public function formatColor($string, ...$colors) {
		if($this->isColor && $colors) {
			echo "\033[", implode(';', $colors), 'm', $string, "\033[0m";
		} else {
			echo $string;
		}
	}
	
	public function beginColor(...$colors) {
		if($this->isColor && $colors) echo "\033[", implode(';', $colors), 'm';
	}
	
	public function endColor() {
		echo "\033[0m";
	}
	
	/**
	 * @param mixed $string
	 * @param int ...$colors
	 * @return string|mixed
	 */
	public function asFormatColor($string, ...$colors) {
		return $this->isColor && ($colors = implode(';', $colors)) !== '' ? "\033[{$colors}m{$string}\033[0m" : $string;
	}
}