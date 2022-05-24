<?php
namespace fwe\utils;

use fwe\base\Exception;

abstract class StringHelper {

	/**
	 * 格式化标签名为数组
	 *
	 * @param array $keywords
	 *
	 * @return array
	 */
	public static function participle($keywords) {
		if (! is_array($keywords)) {
			$keywords = preg_replace("/(,|\s|;|　|，|；)+/", ',', trim((string) $keywords));
			
			$keywords = trim($keywords, ',');
			
			if (empty($keywords)) {
				return false;
			}
			$keywords = explode(',', $keywords);
		}
		if (count($keywords) == 0) {
			return false;
		}
		
		return array_unique($keywords);
	}

	/**
	 * 把字符串转换为MySqL的like查询表达式值
	 *
	 * @param string $str
	 * @param string $prefix
	 * @param string $suffix
	 * @return string
	 */
	public static function str2like($str, $prefix = '%', $suffix = '%') {
		return $prefix . strtr($str, array (
			'%' => '\%',
			'_' => '\_',
			'\\' => '\\\\' 
		)) . $suffix;
	}

	/**
	 * 返回字符串的长度
	 *
	 * @param string $str
	 * @param string $charset
	 * @return integer
	 */
	public static function strlen($str, $charset = 'UTF-8') {
		return mb_strlen($str, $charset);
	}

	/**
	 * 返回字符串的子串
	 *
	 * @param string $str
	 * @param integer $start
	 * @param integer $len
	 * @param string $charset
	 * @return string
	 */
	public static function substr($str, $start, $len, $charset = 'UTF-8') {
		return mb_substr($str, $start, $len, $charset);
	}

	/**
	 * 截取指定长度的字符串
	 *
	 * @param string $str
	 * @param integer $len
	 * @param string $charset
	 * @return string
	 */
	public static function strcut($str, $len, $charset = 'UTF-8') {
		$str = preg_replace("/\s+/", ' ', $str);
		return self::strlen($str, $charset) > $len ? self::substr($str, 0, $len, $charset) . '...' : $str;
	}

	/**
	 * 获取子字符串出现的次数
	 *
	 * @param string $haystack
	 * @param string $needle
	 * @param string $charset
	 * @return integer
	 */
	public static function substrCount($haystack, $needle, $charset = 'UTF-8') {
		return mb_substr_count($haystack, $needle, $charset);
	}

	/**
	 * 从字符串中去除 HTML 和 PHP 标记
	 *
	 * @param mixed $str 可以是数组或字符串
	 * @return string
	 */
	public static function stripTags($str, $allowable_tags = null) {
		if (is_array($str)) {
			foreach ( $str as $k => $v ) {
				$str[$k] = self::stripTags($v);
			}
			return $str;
		}
		
		return self::stripTagsEx(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($str, $allowable_tags))));
	}

	/**
	 * 转HTML字符为全角字符
	 *
	 * @param mixed $str 可以是数组或字符串
	 * @return string
	 */
	public static function stripTagsEx($str) {
		if (is_array($str)) {
			foreach ( $str as $k => $v ) {
				$str[$k] = self::stripTagsEx($v);
			}
			return $str;
		}
		
		$str = trim($str);
		$str = preg_replace('/&([a-z]+);/i', '＆\1;', $str);
		$str = str_replace('<', '«', $str);
		$str = str_replace('>', '»', $str);
		$str = preg_replace('/\s*[\r\n]+\s*/', "\n", $str);
		$str = preg_replace('/[^\S\n]+/', ' ', $str);
		return $str;
	}

	/**
	 * 过滤HTML中的脚本，包括script标记和事件属性
	 *
	 * @param mixed $str
	 * @return string
	 */
	public static function stripScriptTags($str) {
		if (is_array($str)) {
			foreach ( $str as $k => $v ) {
				$str[$k] = self::stripScriptTags($v);
			}
			return $str;
		}
		
		$str = preg_replace('/\s+/', ' ', $str);
		$str = preg_replace('/\<(\/?)(script|i?frame|style|html|body|title|link|meta)([^>]*?)\>/isu', '<$1pre>', $str);
		$str = preg_replace_callback('/\<(\w+)(\s+[^\>]+)\>/', function ($mathes) {
			return sprintf('<%s%s>', $mathes[1], preg_replace(array (
				'/\s+on\w+\s*=\s*"[^"]*"/i',
				'/\s+on\w+\s*=\s*\'[^\']*\'/i',
				'/\s+on\w+\s*=[^\s]*/i',
				'/\s+style\s*=\s*"[^"]*expression[^"]*"/i',
				'/\s+style\s*=\s*\'[^\']*expression[^\']*\'/i',
				'/\s+style\s*=\s*[^\s]*expression[^\s]*/i',
				'/\s+href\s*=\s*"javascript:[^"]+"/i',
				'/\s+href\s*=\s*\'javascript:[^\']+\'/i',
				'/\s+href\s*=\s*javascript:[^\s]*/i' 
			), '', $mathes[2]));
		}, $str);
		$str = trim($str);
		return $str;
	}

	/**
	 * 删除字符串前端的空白字符（或者其他字符）
	 *
	 * @param mixed $str 可以是数组或字符串
	 * @param string $charlist
	 * @return string
	 */
	public static function ltrim($str, $charlist = null) {
		if (is_array($str)) {
			foreach ( $str as $k => $v ) {
				$str[$k] = self::ltrim($v, $charlist);
			}
			return $str;
		}
		return ltrim($str, $charlist);
	}

	/**
	 * 删除字符串末端的空白字符（或者其他字符）
	 *
	 * @param mixed $str 可以是数组或字符串
	 * @param string $charlist
	 * @return string
	 */
	public static function rtrim($str, $charlist = null) {
		if (is_array($str)) {
			foreach ( $str as $k => $v ) {
				$str[$k] = self::rtrim($v, $charlist);
			}
			return $str;
		}
		return rtrim($str, $charlist);
	}

	/**
	 * 删除字符串首尾的空白字符（或者其他字符）
	 *
	 * @param mixed $str 可以是数组或字符串
	 * @param string $charlist
	 * @return string
	 */
	public static function trim($str, $charlist = null) {
		if (is_array($str)) {
			foreach ( $str as $k => $v ) {
				$str[$k] = self::trim($v, $charlist);
			}
			return $str;
		}
		return trim($str, $charlist);
	}

	/**
	 * 字符串转HTML格式
	 *
	 * @param string $string
	 * @return string
	 */
	public static function str2html($string) {
		return str_replace(array (
			' ',
			"\r\n",
			"\r",
			"\n",
			"\t" 
		), array (
			'&nbsp;',
			'<br/>',
			'<br/>',
			'<br/>',
			' &nbsp; &nbsp;' 
		), htmlspecialchars($string));
	}

	/**
	 * XML中CDATA的数据格式化编码
	 *
	 * @param string $string
	 * @return string
	 */
	public static function cData($string) {
		return '<![CDATA[' . str_replace(array (
			'<![CDATA[',
			']]>' 
		), array (
			'&lt;![CDATA[',
			']]&gt;' 
		), $string) . ']]>';
	}

	/**
	 * 获取独一无二的追踪ID
	 *
	 * @return string $tkid
	 */
	public static function tkid() {
		$hyphen = chr(45);
		$charid = strtoupper(md5(uniqid(rand(), true)));
		$ran = rand(1111, 9999);
		$tkid = substr($charid, 0, 8) . $hyphen . substr($charid, 8, 4) . $hyphen . substr($charid, 12, 4) . $hyphen . $ran . $hyphen . substr($charid, 20, 12);
		return $tkid;
	}
	
	public static function int2chinese($num) {
		if($num == 0) {
			return '零';
		}
		$nums = ['零', '一', '二', '三', '四', '五', '六', '七', '八', '九'];
		$ms = [null, '十', '百', '千'];
		$Ms = [null, '万', '亿', '万亿', '亿亿', '万亿亿'];
		$m = 0;
		$M = 0;
		
		$ret = null;
		$iStr = ltrim(sprintf('%d', $num), '-');
		$len = strlen($iStr);
		for($i=$len-1; $i>=0; $i--) {
			$n = $iStr[$i];
			if($m == 4) {
				$m=0;
				$M++;
				$ret = $Ms[$M] . $ret;
			}
			$ret = $nums[$n] . ($n ? $ms[$m] : null) . $ret;
			$m++;
		}
		
		$ret = preg_replace('/(零)+/', '零', $ret);
		$ret = preg_replace('/零(?!一|二|三|四|五|六|七|八|九)/', '', $ret);
		$ret = str_replace('一十', '十', $ret);
		
		return ($num<0 ? '负' : null) . $ret;
	}
	
	/**
	 * 文件大小相互转换(1K=>1024,1024=>1K)
	 * 
	 * @param number|string $size
	 * @return number|string
	 */
	public static function formatBytes($size) {
		if(!is_numeric($size)) {
			$matches = [];
			if(preg_match('/^(\d+(\.\d+)?)([KMGT]?)B?$/i', $size, $matches)) {
				return $matches[1] * pow(1024, $matches[3] ? strpos('KMGT', strtoupper($matches[3]))+1 : 0);
			} else {
				return round($size+0, 0);
			}
		}
		$units = array('', 'K', 'M', 'G', 'T');
		for ($i = 0; $size >= 1024 && $i < 4; $i++) $size /= 1024;
		return round($size, 3).$units[$i];
	}
	
	public static function xml2array(string $xml) {
		$parser = xml_parser_create();
		xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
		xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 1);
		$values = [];
		$ret = xml_parse_into_struct($parser, $xml, $values);
		if(!$ret) {
			$code = xml_get_error_code($parser);
			$e = new Exception(xml_error_string($code), [
				'line' => xml_get_current_line_number($parser),
				'column' => xml_get_current_column_number($parser),
				'index' => xml_get_current_byte_index($parser),
			], $code);
			
			xml_parser_free($parser);
			
			throw $e;
		} else {
			xml_parser_free($parser);
		}
		
		$return = [];
		$stack = [];
		foreach($values as $val) {
			if($val['type'] == "open") {
				array_push($stack, $val['tag']);
			} elseif($val['type'] == "close") {
				array_pop($stack);
			} elseif($val['type'] == "complete") {
				array_push($stack, $val['tag']);
				$_stack = $stack;
				$ret = &$return;
				while($_stack) {
					$key = array_shift($_stack);
					$ret2 = &$ret[$key];
					unset($ret);
					$ret = &$ret2;
					unset($ret2);
				}
				if(isset($val['attributes'])) {
					$ret = $val['attributes'];
					if(isset($ret['value'])) {
						$ret['@value'] = $val['value'] ?? null;
					} else {
						$ret['value'] = $val['value'] ?? null;
					}
				} else {
					$ret = $val['value'] ?? '';
				}
				unset($ret);
				array_pop($stack);
			}
		}
		
		if(count($return) > 1) {
			return $return;
		} else {
			return reset($return);
		}
	}
}
