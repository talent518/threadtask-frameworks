<?php
namespace fwe\http;

/**
 * @property-read array $properties
 * 
 * @property-read string $method
 * @property-read array $headers
 * @property-read integer $type
 * @property-read string|array $data
 * @property-read array $form
 * @property-read string $file
 * @property-read integer $fileSize
 * @property-read integer $format
 * 
 * @property-read float $readTimeout
 * @property-read float $writeTimeout
 * 
 * @property-read string $responseProtocol
 * @property-read integer $responseStatus
 * @property-read string $responseStatusText
 * @property-read array $responseHeaders
 * @property-read string $responseData
 * @property-read integer $responseLength
 * 
 * @property-read float $runTime
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
	
	protected $readTimeout = 30, $writeTimeout = 30;
	protected $saveFile, $saveAppend;
	
	protected $responseProtocol, $responseStatus, $responseStatusText, $responseHeaders = [], $responseData, $responseLength = 0;
	
	protected $runTime, $connTime;
	
	public function __construct(string $url, string $method = 'GET', array $headers = [], string $protocol = 'HTTP/1.1') {
		$this->url = $url;
		$this->method = strtoupper($method);
		$this->headers = $headers;
		$this->protocol = $protocol;
	}
	
	public function __destruct() {
		\Fwe::debug(get_called_class(), $this->url, true);
	}
	
	public function jsonSerialize() {
		return get_object_vars($this);
	}
	
	public function __toString() {
		$json = json_encode($this, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
		if(json_last_error()) {
			\Fwe::$app->warn('json_encode error: ' . json_last_error_msg(), 'http-client');
			
			return var_export($this, true);
		} else {
			return $json;
		}
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
		
		return $this;
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
	
	public function setTimeout(float $readTimeout, float $writeTimeout) {
		$this->readTimeout = $readTimeout;
		$this->writeTimeout = $writeTimeout;
		
		return $this;
	}
	
	private $_time;
	private $_head, $_event;
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
		$this->_head = "$head\r\n";
		
		$this->_ok = $ok;
		\Fwe::$app->events ++;
		
		$this->_time = microtime(true);
		$this->connTime = 0;
		
		$is_ssl = ($scheme === 'https');
		$this->_event = Event::connect($uri['host'], $uri['port'] ?? ($is_ssl ? 443 : 80), $is_ssl);
		$this->_event->setRequest($this, $this->_bodylen > 0, $this->readTimeout, $this->writeTimeout);
		$this->_event->write($this->_head) or printf("write head error\n");
	}
	
	public function saveFile(string $file, bool $append = false) {
		$this->saveFile = $file;
		$this->saveAppend = $append;
		
		return $this;
	}
	
	private $_responseHandler;
	public function setResponseHandler(callable $handler) {
		$this->_responseHandler = $handler;
		
		return $this;
	}
	
	private $_saveFp;
	public function onResponse(string $buf, int $n) {
		if($this->_responseHandler) {
			call_user_func($this->_responseHandler, $buf, $n);
		} elseif($this->saveFile) {
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
	
	public function connected() {
		$this->connTime = round(microtime(true) - $this->_time, 6);
	}
	
	public function sendBody() {
		// echo "send body: {$this->_bodylen} {$this->_bodyoff}\n";
		if($this->_bodylen > $this->_bodyoff) {
			if($this->_bodyFp) {
				$buf = fread($this->_bodyFp, min(16 * 1024, $this->_bodylen - $this->_bodyoff));
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
	
	public function isKeepAlive() {
		return isset($this->responseHeaders['Connection']) && !strcasecmp($this->responseHeaders['Connection'], 'keep-alive');
	}
	
	public function setResponseStatus(string $protocol, int $status, string $statusText) {
		$this->responseProtocol = $protocol;
		$this->responseStatus = $status;
		$this->responseStatusText = $statusText;
		$this->responseHeaders = [];
	}
	
	public function responseContentLength() {
		return (int) ($this->responseHeaders['Content-Length'] ?? 0);
	}
	
	public function responseTransferEncoding() {
		return (isset($this->responseHeaders['Transfer-Encoding']) && $this->responseHeaders['Transfer-Encoding'] === 'chunked');
	}
	
	public function responseContentEncoding() {
		return $this->responseHeaders['Content-Encoding'] ?? null;
	}
	
	public function addResponseHeader(string $name, string $value) {
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
	
	public function ok(int $errno, string $error) {
		if(!$this->_event) return;

		\Fwe::$app->events --;
		$this->runTime = round(microtime(true) - $this->_time, 6);
		$this->_head = $this->_body = null;
		$this->_bodyFp = null;
		$this->_responseHandler = $this->_event = null;
		
		// printf("connTime: %.6f, runTime: %.6f\n", $this->connTime, $this->runTime);
		
		$ok = $this->_ok;
		$this->_ok = null;
		
		try {
			call_user_func($ok, $errno, $error);
		} catch(\Throwable $e) {
			\Fwe::$app->error($e, 'http-client');
		}
	}
	
	public function free(int $errno, string $error) {
		if($this->_event) {
			$this->_event->setRequest(null, false, -1, -1);
			$this->_event->free($errno, $error);
		}
	}
	
}
