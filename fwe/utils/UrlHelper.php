<?php

namespace fwe\utils;

class UrlHelper {

	public $scheme;

	public $host;

	public $port;

	public $user;

	public $pass;

	public $path;

	/**
	 *
	 * @var array
	 */
	public $gets;

	public $fragment;

	public function __construct(array $uri = array()) {
		$this->fromArray($uri);
	}

	public function fromArray(array $uri) {
		foreach ( $uri as $k => $v ) {
			if (property_exists($this, $k)) {
				$this->$k = $v;
			} elseif ($k === 'query' && is_string($v)) {
				@parse_str($v, $this->gets);
			}
		}
	}

	public function __toString() {
		$url = '';
		if ($this->scheme) {
			$url .= $this->scheme . '://';
		}
		if ($this->user !== null && $this->user !== '') {
			$url .= $this->user;
			if ($this->pass !== null && $this->pass !== '') {
				$url .= ':' . $this->pass;
			}
			$url .= '@';
		}
		if ($this->host !== null && $this->host !== '') {
			$url .= $this->host;
		}
		if ($this->port > 0) {
			$url .= ':' . $this->port;
		}
		if ($this->path !== null) {
			$url .= '/' . ltrim($this->path, '/');
		}
		if (is_array($this->gets) && count($this->gets)) {
			$url .= '?' . http_build_query($this->gets);
		}
		if ($this->fragment !== null && $this->fragment !== '') {
			$url .= '#' . $this->fragment;
		}
		return $url;
	}

	/**
	 * 粘贴URL为UrlHelper对象
	 *
	 * @param string $url
	 * @return UrlHelper
	 */
	public static function parseUrl($url) {
		$uri = @parse_url($url);
		return new UrlHelper(is_array($uri) ? $uri : array ());
	}
	
	public static function relative(string $url, string $dest) {
		if(preg_match('/^[a-z]+:\/\/\w+/', $dest)) {
			return $dest;
		} else {
			$uri = static::parseUrl($url);
			$uri2 = static::parseUrl($dest);
			if($uri2->host !== null && $uri2->host !== '') {
				if($uri2->scheme) {
					$uri->scheme = $uri2->scheme;
				}
				$uri->host = $uri2->host;
				$uri->port = $uri2->port;
				$uri->user = $uri2->user;
				$uri->pass = $uri2->pass;
			}
			if($uri2->path !== null && $uri2->path !== '') {
				if($uri->path !== null && $uri->path !== '') {
					if(strncmp($uri2->path, '/', 1) && ($pos = strrpos($uri->path, '/')) !== false) {
						$uri->path = str_replace('/./', '/', substr($uri->path, 0, $pos + 1) . $uri2->path);
						do {
							$path = $uri->path;
							$uri->path = preg_replace('/(\/[^\/]+)?\/\.\.(\/|$)/', '/', $uri->path);
						} while($path !== $uri->path);
						$uri->path = preg_replace('/\/\.$/', '/', $uri->path);
					} else {
						$uri->path = $uri2->path;
					}
				} else {
					$uri->path = $uri2->path;
				}
			} else {
				$uri->path = null;
			}
			
			$uri->gets = $uri2->gets;
			$uri->fragment = $uri2->fragment;
			
			return (string) $uri;
		}
	}

	/**
	 * 数组(由parse_url生成)或UrlHelper对象转换为URL
	 *
	 * @param mixed $uri
	 * @return string
	 */
	public static function buildUrl($uri) {
		if (is_array($uri)) {
			return strval(new UrlHelper($uri));
		} else {
			return strval($uri);
		}
	}

}