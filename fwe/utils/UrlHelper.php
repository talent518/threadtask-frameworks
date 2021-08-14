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
		if ($this->user) {
			$url .= $this->user;
			if ($this->pass) {
				$url .= ':' . $this->pass;
			}
			$url .= '@';
		}
		if ($this->host) {
			$url .= $this->host;
		}
		if ($this->port) {
			$url .= ':' . $this->port;
		}
		if ($this->path) {
			$url .= '/' . ltrim($this->path, '/');
		}
		if (is_array($this->gets) && count($this->gets)) {
			$url .= '?' . http_build_query($this->gets);
		}
		if ($this->fragment) {
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