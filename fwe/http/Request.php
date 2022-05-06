<?php
namespace fwe\http;

/**
 * @property-read string $method
 * @property-read array $headers
 * @property-read integer $type
 * @property-read string|array $data
 * @property-read array $form
 * @property-read string $file
 * @property-read integer $fileSize
 * @property-read integer $format
 * 
 * @property-read string $responseProtocol
 * @property-read integer $responseStatus
 * @property-read string $responseStatusText
 * @property-read array $responseHeaders
 * @property-read string $responseData
 * @property-read integer $responseLength
 */
class Request implements \JsonSerializable {
	const TYPE_NONE = 0;
	const TYPE_DATA = 1;
	const TYPE_FORM = 2;
	const TYPE_FILE = 3;
	
	const FORMAT_URL = 0;
	const FORMAT_JSON = 1;
	const FORMAT_XML = 2;
	const FORMAT_RAW = 3;
	
	protected $url, $method, $protocol, $headers;
	protected $type = self::TYPE_NONE;
	
	protected $responseProtocol, $responseStatus, $responseStatusText, $responseHeaders = [], $responseData, $responseLength, $saveFile, $saveAppend;
	
	public function __construct(string $url, string $method = 'GET', array $headers = [], string $protocol = 'HTTP/1.1') {
		$this->url = $url;
		$this->method = strtoupper($method);
		$this->headers = $headers;
		$this->protocol = $protocol;
	}
	
	public function jsonSerialize() {
		return get_object_vars($this);
	}
	
	public function __toString() {
		return json_encode($this, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
// 		return var_export($this, true);
	}
	
	public function __get($name) {
		if($name === 'properties') {
			return get_object_vars($this);
		} else {
			return $this->$name;
		}
	}
	
	public function getHeader(string $name, ?string $defVal = null) {
		return $this->headers[$name] ?? $defVal;
	}
	
	public function addHeader(string $name, string $value) {
		if(isset($this->headers[$name])) {
			if(is_array($this->headers[$name])) {
				$this->headers[$name][] = $value;
			} else {
				$this->headers[$name] = [$this->headers[$name], $value];
			}
		} else {
			$this->headers[$name] = $value;
		}
		
		return $this;
	}
	
	public function addHeaders(array $headers) {
		foreach($headers as $name => $value) {
			$this->addHeader($name, $value);
		}
	}
	
	protected $data;
	public function setData($data) {
		$this->type = self::TYPE_DATA;
		$this->data = $data;
		$this->form = null;
		
		return $this;
	}
	
	public function addData($name, $value) {
		$this->data[$name] = $value;
		
		return $this;
	}
	
	protected $form = [];
	public function setForm(array $form, bool $isAppend = false) {
		$this->type = self::TYPE_FORM;
		
		if(!$isAppend) $this->form = [];
		foreach($form as $name => $value) {
			$this->addForm($name, $value);
		}
		
		return $this;
	}
	
	public function addForm(string $name, $value) {
		$this->type = self::TYPE_FORM;
		$this->data = null;
		
		if($value instanceof File || is_string($value) || is_int($value) || is_float($value)) {
			$this->form[$name] = $value;
		} elseif(is_object($value) && method_exists($value, '__toString')) {
			$this->form[$name] =  (string) $value;
		} else {
			$this->form[$name] = null;
		}
		
		return $this;
	}
	
	protected $file, $fileOff, $fileSize;
	public function setFile(string $file, int $off = 0, int $size = -1) {
		$this->type = self::TYPE_FILE;
		$this->data = null;
		$this->form = null;
		$this->file = $file;
		$this->fileOff = $off;
		$this->fileSize = ($size < 0 ? filesize($file) : $size);
		
		return $this;
	}
	
	protected $format = self::FORMAT_URL;
	public function setFormat(int $format) {
		$this->format = $format;
		
		switch($format) {
			case self::FORMAT_URL:
			case self::FORMAT_JSON:
			case self::FORMAT_XML:
			case self::FORMAT_RAW:
				break;
			default:
				$this->format = self::FORMAT_URL;
				break;
		}
		
		return $this;
	}
	
	protected function makeXML(\SimpleXMLElement $xml, array $a) {
		foreach($a as $k => $v) {
			if(is_object($v)) {
				$this->makeXML($xml->addChild($k), get_object_vars($v));
			} elseif(is_array($v)) {
				$this->makeXML($xml->addChild($k), $v);
			} else {
				$xml->addChild($k, $v);
			}
		}
	}
	
	protected function format(): string {
		switch($this->format) {
			case self::FORMAT_URL:
			default:
				if($this->data !== null) {
					$this->headers['Content-Type'] = 'application/x-www-form-urlencoded';
					return http_build_query($this->data);
				}
			case self::FORMAT_JSON:
				$this->headers['Content-Type'] = 'application/json';
				return json_encode($this->data, JSON_PRETTY_PRINT);
			case self::FORMAT_XML:
				$this->headers['Content-Type'] = 'application/xml; charset=utf-8';
				$xml = new \SimpleXMLElement("<?xml version=\"1.0\"?><root></root>");
				if(is_array($this->data)) {
					$this->makeXML($xml, $this->data);
				}
				return $xml->asXML();
			case self::FORMAT_RAW:
				return (string) $this->data;
		}
	}
	
	private $_body, $_bodyFp, $_bodylen;
	protected function makeBody() {
		switch($this->type) {
			case self::TYPE_DATA:
				$this->_body = is_string($this->data) ? $this->data : $this->format();
				$this->_bodyFp = null;
				$this->headers['Content-Length'] = $this->_bodylen = ($this->_body === null ? 0 : strlen($this->_body));
				break;
			case self::TYPE_FORM:
				$boundary = str_pad(bin2hex(random_bytes(8)), 40, '-', STR_PAD_LEFT);
				$this->headers['Content-Type'] = 'multipart/form-data; boundary=' . $boundary;
				$this->_body = null;
				foreach($this->form as $name => $value) {
					if($value instanceof File) {
						$cont = file_get_contents($value->file);
						$mime = $value->mime ?: 'application/octet-stream';
						$this->_body .= "--$boundary\r\nContent-Disposition: form-data; name=\"$name\"\r\nContent-Type: $mime\r\n\r\n$cont\r\n";
						unset($cont, $mime);
					} else {
						$this->_body .= "--$boundary\r\nContent-Disposition: form-data; name=\"$name\"\r\n\r\n$value\r\n";
					}
				}
				$this->_body .= "--$boundary--\r\n";
				$this->_bodyFp = null;
				$this->headers['Content-Length'] = $this->_bodylen = strlen($this->_body);
				break;
			case self::TYPE_FILE:
				$this->headers['Content-Length'] = $this->fileSize;
				$this->_body = null;
				if($this->fileSize > 0) {
					$this->_bodyFp = fopen($this->file, 'r');
					if($this->_bodyFp) {
						fseek($this->_bodyFp, $this->fileOff, SEEK_SET);
					}
					$this->_bodylen = $this->fileSize;
				} else {
					$this->_bodyFp = null;
					$this->headers['Content-Length'] = $this->_bodylen = 0;
				}
				break;
			default:
				$this->_body = $this->_bodyFp = null;
				$this->headers['Content-Length'] = $this->_bodylen = 0;
				break;
		}
	}
	
	private static $_events = [];
	private $_key;
	private $_ssl_ctx, $_event, $_ok;
	public function send(callable $ok) {
		$uri = parse_url($this->url);
		if(!$uri) {
			call_user_func($ok, -1, "URL {$this->url} 不合法");
			return;
		}
		
		$scheme = $uri['scheme'] ?? 'http';
		if($scheme !== 'http' && $scheme !== 'https') {
			call_user_func($ok, -2, "不支持协议 $scheme 在URL {$this->url} 中");
			return;
		}
		
		$url = ($uri['path'] ?? '/') . (isset($uri['query']) ? "?{$uri['query']}" : null) . (isset($uri['fragment']) ? "#{$uri['fragment']}" : null);
		
		$this->_ssl_ctx = null;
		if($scheme === 'https') {
			try {
				$this->_ssl_ctx = new \EventSslContext(\EventSslContext::SSLv23_CLIENT_METHOD, []);
			} catch(\Throwable $e) {
				try {
					$this->_ssl_ctx = new \EventSslContext(\EventSslContext::SSLv3_CLIENT_METHOD, []);
				} catch(\Throwable $e) {
					try {
						$this->_ssl_ctx = new \EventSslContext(\EventSslContext::SSLv2_CLIENT_METHOD, []);
					} catch(\Throwable $e) {
						try {
							$this->_ssl_ctx = new \EventSslContext(\EventSslContext::TLSv11_CLIENT_METHOD, []);
						} catch(\Throwable $e) {
							try {
								$this->_ssl_ctx = new \EventSslContext(\EventSslContext::TLSv12_CLIENT_METHOD, []);
							} catch(\Throwable $e) {
								$this->_ssl_ctx = new \EventSslContext(\EventSslContext::TLS_CLIENT_METHOD, []);
							}
						}
					}
				}
			}
		}
		
		if($this->_ssl_ctx) {
			$this->_event = \EventBufferEvent::sslSocket(\Fwe::$base, NULL, $this->_ssl_ctx, \EventBufferEvent::SSL_CONNECTING, \EventBufferEvent::OPT_CLOSE_ON_FREE);
		} else {
			$this->_event = new \EventBufferEvent(\Fwe::$base, null, \EventBufferEvent::OPT_CLOSE_ON_FREE);
		}
		$this->_event->setCallbacks([$this, 'readHandler'], [$this, 'writeHandler'], [$this, 'eventHandler']);
		$this->_event->connectHost(null, $uri['host'], $uri['port'] ?? ($this->_ssl_ctx ? 443 : 80));
		$this->_event->setTimeouts(30, 30);
		$this->_event->enable(\Event::READ);
		
		$head = "{$this->method} {$url} {$this->protocol}\r\n";
		if(!isset($this->headers['Host'])) {
			$this->headers['Host'] = isset($uri['port']) ? "{$uri['host']}:{$uri['port']}" : $uri['host'];
		}
		if(!isset($this->headers['Accept'])) {
			$this->headers['Accept'] = '*/*';
		}
		if(!isset($this->headers['User-Agent'])) {
			$this->headers['User-Agent'] = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/101.0.4951.41 Safari/537.36';
		}
		$this->headers['Accept-Encoding'] ='gzip, deflate';
		if(!isset($this->headers['Accept-Language'])) {
			$this->headers['Accept-Language'] ='zh-CN,zh;q=0.9,en;q=0.8';
		}
		$this->makeBody();
		if($this->_bodylen > 0) {
			$this->headers['Expect'] = '100-continue';
		}
		foreach($this->headers as $name => $value) {
			foreach((array) $value as $v) {
				$head .= "$name: $v\r\n";
			}
		}
		$this->_event->write("$head\r\n") or printf("write head error\n");
		
		$this->_ok = $ok;
		\Fwe::$app->events ++;
		
		self::$_events[] = $this;
		$this->_key = array_key_last(self::$_events);
		$this->_isHeadSent = false;
		$this->_isReadBody = false;
		$this->_isExpect = $this->_bodylen > 0;
		$this->_isFirstHead = true;
		$this->_isChunked = false;
		$this->_readBuf = $this->_inflate = null;
		$this->_bodyoff = $this->responseLength = $this->_readLen = 0;
		$this->_chunkLen = -1;
	}
	
	public function saveFile(string $file, bool $append = false) {
		$this->saveFile = $file;
		$this->saveAppend = $append;
	}
	
	private $_saveFp;
	protected function onResponse(string $buf, int $n) {
		if($this->saveFile) {
			if($this->responseStatus >= 200 && $this->responseStatus < 300) {
				if($this->_saveFp === null) {
					$this->_saveFp = fopen($this->saveFile, $this->saveAppend ? 'a' : 'w');
				}
				fwrite($this->_saveFp, $buf, $n);
			} else {
				$this->responseData .= $buf;
			}
		} else {
			$this->responseData .= $buf;
		}
		$this->responseLength += $n;
	}
	
	private $_isReadBody, $_isExpect, $_isFirstHead, $_isChunked, $_readBuf, $_readLen, $_chunkLen, $_inflate;
	public function readHandler($bev, $arg) {
		$buf = $this->_event->read(16 * 1024);
		$n = strlen($buf);
		// echo "read handler: $n\n";
		if($this->_isReadBody) {
			$this->_readBuf .= $buf;
			body:
			if($this->_isChunked) {
				$i = 0;
				$n = strlen($this->_readBuf);
				while($i < $n) {
					if($this->_chunkLen < 0) {
						if(($pos = strpos($this->_readBuf, "\r\n", $i)) === false) {
							$this->free(-4, 'Chunked size error');
							break;
						} else {
							if($i === $pos) {
								if($i + 5 > $n) {
									$this->_readBuf = substr($this->_readBuf, $i + 2);
									break;
								} else {
									$i += 2;
								}
							}
							$buf = substr($this->_readBuf, $i, $pos - $i);
							$this->_chunkLen = (int) base_convert($buf, 16, 10);
							// printf("chunkLen: %d, %d, %d, %d, %s\n", $this->_chunkLen, $i, $pos, $n, bin2hex(substr($this->_readBuf, 0, 20)));
							$i = $pos + 2;
							if($this->_chunkLen <= 0) {
								$this->free();
								break;
							}
						}
					} else {
						$_n = $n - $i;
						if($_n === $this->_chunkLen + 1) { // 防止块结束后的\r\n不够现象
							$this->_readBuf = substr($this->_readBuf, $i);
							// echo "---222\n";
							break;
						}
						$buf = substr($this->_readBuf, $i, $this->_chunkLen);
						$_n = strlen($buf);
						$i += $_n;
						$this->_chunkLen -= $_n;
						if($this->_chunkLen === 0) {
							$this->_chunkLen = -1;
							$i += 2;
						}
						if($this->_inflate) {
							$buf = @inflate_add($this->_inflate, $buf);
							if($buf !== false) {
								$this->onResponse($buf, strlen($buf));
							}
						} else {
							$this->onResponse($buf, $_n);
						}
					}
				}
				if($i >= $n) {
					$this->_readBuf = null;
				}
			} else {
				if($this->_inflate) {
					$buf = @inflate_add($this->_inflate, $this->_readBuf);
					if($buf !== false) {
						$this->onResponse($buf, strlen($buf));
					}
				} else {
					$this->onResponse($this->_readBuf, strlen($this->_readBuf));
				}
				$this->_readBuf = null;
				if($this->responseLength >= $this->_readLen) {
					$this->free();
				}
			}
		} else {
			$this->_readBuf .= $buf;
			$n = strlen($this->_readBuf);
			$i = 0;
			while($i < $n) {
				if(($pos = strpos($this->_readBuf, "\r\n", $i)) === false) {
					if($i > 0) {
						$this->_readBuf = substr($this->_readBuf, $i);
					}
					break;
				} elseif($pos === $i) {
					$this->_readBuf = substr($this->_readBuf, $i + 2);
					if($this->_isFirstHead) {
						$i += 2;
						continue;
					}
					$this->responseData = null;
					$this->responseLength = 0;
					$this->_isReadBody = true;
					$this->_readLen = (int) ($this->responseHeaders['Content-Length'] ?? 0);
					$this->_isChunked = (isset($this->responseHeaders['Transfer-Encoding']) && $this->responseHeaders['Transfer-Encoding'] === 'chunked');
					$this->_chunkLen = -1;
					switch($this->responseHeaders['Content-Encoding'] ?? null) {
						case 'deflate':
							$this->_inflate = inflate_init(ZLIB_ENCODING_DEFLATE);
							break;
						case 'gzip':
							$this->_inflate = inflate_init(ZLIB_ENCODING_GZIP);
							break;
						default:
							break;
					}
					goto body;
					break;
				} else {
					$line = substr($this->_readBuf, $i, $pos - $i);
					$i = $pos + 2;
					if($this->_isFirstHead) {
						list($this->responseProtocol, $status, $statusText) = preg_split('/\s+/', $line, 3);
						$this->responseStatus = (int) $status;
						$this->responseStatusText = trim($statusText);
						$this->responseHeaders = [];
						if($this->_isExpect) {
							$this->_isExpect = false;
							$this->_isFirstHead = ($this->responseStatus === 100);
							if($this->_isFirstHead) {
								$this->sendBody();
							}
						} else {
							$this->_isFirstHead = false;
						}
					} else {
						@list($name, $value) = preg_split('/:\s*/', $line, 2);
						if(isset($this->responseHeaders[$name])) {
							$values = &$this->responseHeaders[$name];
							if(is_array($values)) {
								$values[] = $value;
							} else {
								$values = [$values, $value];
							}
							unset($values);
						} else {
							$this->responseHeaders[$name] = $value;
						}
					}
				}
			}
			if($i >= $n) {
				$this->_readBuf = null;
			}
		}
	}
	
	private $_isHeadSent, $_bodyoff;
	public function writeHandler($bev, $arg) {
		// $sent = ($this->_isHeadSent ? 'body' : 'head');
		// echo "write handler: {$sent}\n";
		if($this->_isHeadSent) {
			$this->sendBody();
		} else {
			$this->_isHeadSent = true;
		}
	}
	
	private function sendBody() {
		// echo "send body: {$this->_bodylen} {$this->_bodyoff}\n";
		if($this->_bodylen > $this->_bodyoff) {
			if($this->_bodyFp) {
				$buf = fread($this->_body, min(16 * 1024, $this->_bodylen - $this->_bodyoff));
			} else {
				$buf = substr($this->_body, $this->_bodyoff, 16 * 1024);
			}
			$this->_event->write($buf) or printf("write body error\n");
			$this->_bodyoff += strlen($buf);
			if($this->_bodyoff <= 0) {
				$this->body = $this->_bodyFp = null;
			}
		}
	}
	
	public function eventHandler($bev, $event, $arg) {
		// echo "event: $event\n";
		if($event & \EventBufferEvent::EOF) {
			$this->free();
		} elseif($event & \EventBufferEvent::ERROR) {
			$this->free(-3, 'Error');
		}
	}
	
	public function free(int $errno = 0, string $error = 'OK') {
		if(!$this->_event) return;

		$this->_event->free();
		$this->_event = null;
		\Fwe::$app->events --;
		unset(self::$_events[$this->_key]);
		$this->_key = null;
		$this->_readBuf = null;
		$this->_inflate = null;
		$this->_ssl_ctx = null;
		$this->_saveFp = null;
		
		$ok = $this->_ok;
		$this->_ok = null;

		try {
			call_user_func($ok, $errno, $error);
		} catch(\Throwable $e) {
			\Fwe::$app->error($e, 'http-client');
		}
	}
}
