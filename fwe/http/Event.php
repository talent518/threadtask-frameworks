<?php
namespace fwe\http;

class Event {
	protected static $events = [], $isRegister = false;

	protected $keepKey;
	protected $ssl_ctx, $dnsBase;
	
	/**
	 * @var \EventBufferEvent
	 */
	protected $event;
	
	/**
	 * @var Request
	 */
	protected $request;
	
	public static function connect(string $host, int $port, bool $is_ssl, int $keepAlive) {
		$ssl = ($is_ssl ? 1 : 0);
		$key = "$host:$port:$ssl";
		
		if(!static::$isRegister) {
			static::$isRegister = true;
			register_shutdown_function(function() {
				foreach(static::$events as $evts) {
					foreach($evts as $evt) {
						$evt->free();
					}
				}
				static::$events = [];
			});
		}

	retry:
		if(empty(static::$events[$key])) {
			return new Event($host, $port, $is_ssl, $key, $keepAlive);
		} else {
			$event = array_pop(static::$events[$key]);
			
			if(microtime(true) >= $event->keepAlive) {
				$event->free();
			} else {
				goto retry;
			}
		}
	}
	
	private function __construct(string $host, int $port, bool $is_ssl, string $keepKey, int $keepAlive) {
		$this->keepKey = $keepKey;
		$this->keepAlive = microtime(true) + $keepAlive;
		if($is_ssl) {
			try {
				$this->ssl_ctx = new \EventSslContext(\EventSslContext::SSLv23_CLIENT_METHOD, []);
			} catch(\Throwable $e) {
				try {
					$this->ssl_ctx = new \EventSslContext(\EventSslContext::SSLv3_CLIENT_METHOD, []);
				} catch(\Throwable $e) {
					try {
						$this->ssl_ctx = new \EventSslContext(\EventSslContext::SSLv2_CLIENT_METHOD, []);
					} catch(\Throwable $e) {
						try {
							$this->ssl_ctx = new \EventSslContext(\EventSslContext::TLSv11_CLIENT_METHOD, []);
						} catch(\Throwable $e) {
							try {
								$this->ssl_ctx = new \EventSslContext(\EventSslContext::TLSv12_CLIENT_METHOD, []);
							} catch(\Throwable $e) {
								$this->ssl_ctx = new \EventSslContext(\EventSslContext::TLS_CLIENT_METHOD, []);
							}
						}
					}
				}
			}
			
			$this->event = \EventBufferEvent::sslSocket(\Fwe::$base, null, $this->ssl_ctx, \EventBufferEvent::SSL_CONNECTING, \EventBufferEvent::OPT_CLOSE_ON_FREE);
		} else {
			$this->event = new \EventBufferEvent(\Fwe::$base, null, \EventBufferEvent::OPT_CLOSE_ON_FREE);
		}
		
		$this->dnsBase = \Fwe::popDns();
		$this->event->connectHost($this->dnsBase, $host, $port);
		$this->event->setCallbacks([$this, 'readHandler'], [$this, 'writeHandler'], [$this, 'eventHandler']);
		$this->event->enable(\Event::READ);
	}
	
	public function __destruct() {
		\Fwe::debug(get_called_class(), $this->keepKey, true);
	}
	
	protected $isReadBody, $isFirstHead, $isChunked, $readBuf, $readLen, $chunkLen, $inflate;
	protected $isExpect;
	protected $isHeadSent;
	
	protected function reset() {
		$this->isHeadSent = null;
		$this->isReadBody = null;
		$this->isFirstHead = true;
		$this->isChunked = false;
		$this->readBuf = $this->inflate = null;
		$this->readLen = 0;
		$this->chunkLen = -1;
	}
	
	public function setRequest(?Request $req, bool $isExpect, float $readTimeout, float $writeTimeout) {
		$this->reset();
		$this->event->setTimeouts($readTimeout, $writeTimeout);
		$this->request = $req;
		$this->isExpect = $isExpect;
	}
	
	public function write(string $data) {
		return $this->event->write($data);
	}
	
	public function readHandler($bev, $arg) {
		$buf = $this->event->read(16 * 1024);
		$n = strlen($buf);
		// echo "read handler: $n\n";
		if($this->isReadBody) {
			$this->readBuf .= $buf;
			body:
			if($this->isChunked) {
				$i = 0;
				$n = strlen($this->readBuf);
				while($i < $n) {
					if($this->chunkLen < 0) {
						if(($pos = strpos($this->readBuf, "\r\n", $i)) === false) {
							$this->free(-3, 'Chunked size error');
							break;
						} else {
							if($i === $pos) {
								if($i + 5 > $n) {
									$this->readBuf = substr($this->readBuf, $i + 2);
									break;
								} else {
									$i += 2;
								}
							}
							$buf = substr($this->readBuf, $i, $pos - $i);
							$this->chunkLen = (int) base_convert($buf, 16, 10);
							// printf("chunkLen: %d, %d, %d, %d, %s\n", $this->chunkLen, $i, $pos, $n, bin2hex(substr($this->readBuf, 0, 20)));
							$i = $pos + 2;
							if($this->chunkLen <= 0) {
								$this->free();
								break;
							}
						}
					} else {
						$_n = $n - $i;
						if($_n === $this->chunkLen + 1) { // 防止块结束后的\r\n不够现象
							$this->readBuf = substr($this->readBuf, $i);
							// echo "---222\n";
							break;
						}
						$buf = substr($this->readBuf, $i, $this->chunkLen);
						$_n = strlen($buf);
						$i += $_n;
						$this->chunkLen -= $_n;
						if($this->chunkLen === 0) {
							$this->chunkLen = -1;
							$i += 2;
						}
						if($this->inflate) {
							$buf = @inflate_add($this->inflate, $buf);
							if($buf !== false) {
								$this->request->onResponse($buf, strlen($buf));
							}
						} else {
							$this->request->onResponse($buf, $_n);
						}
					}
				}
				if($i >= $n) {
					$this->readBuf = null;
				}
			} else {
				if($this->inflate) {
					$buf = @inflate_add($this->inflate, $this->readBuf);
					if($buf !== false) {
						$this->request->onResponse($buf, strlen($buf));
					}
				} else {
					$this->request->onResponse($this->readBuf, strlen($this->readBuf));
				}
				$this->readBuf = null;
				if($this->request->responseLength >= $this->readLen) {
					$this->free();
				}
			}
		} else {
			$this->readBuf .= $buf;
			$n = strlen($this->readBuf);
			$i = 0;
			while($i < $n) {
				if(($pos = strpos($this->readBuf, "\r\n", $i)) === false) {
					if($i > 0) {
						$this->readBuf = substr($this->readBuf, $i);
					}
					break;
				} elseif($pos === $i) {
					$this->readBuf = substr($this->readBuf, $i + 2);
					if($this->isFirstHead) {
						$i += 2;
						continue;
					}
					$this->isReadBody = true;
					$this->readLen = $this->request->responseContentLength();
					$this->isChunked = $this->request->responseTransferEncoding();
					$this->chunkLen = -1;
					switch($this->request->responseContentEncoding()) {
						case 'deflate':
							$this->inflate = inflate_init(ZLIB_ENCODING_DEFLATE);
							break;
						case 'gzip':
							$this->inflate = inflate_init(ZLIB_ENCODING_GZIP);
							break;
						default:
							break;
					}
					if(strlen($this->readBuf)) {
						goto body;
					} elseif($this->request->method === 'HEAD') {
						$this->request->onResponse('', $this->readLen);
						$this->free();
					} else {
						goto body;
					}
					break;
				} else {
					$line = substr($this->readBuf, $i, $pos - $i);
					$i = $pos + 2;
					if($this->isFirstHead) {
						list($protocol, $status, $statusText) = preg_split('/\s+/', $line, 3);
						$this->request->setResponseStatus($protocol, $status, $statusText);
						if($this->isExpect) {
							$this->isExpect = false;
							$this->isFirstHead = ($status === "100");
							if($this->isFirstHead) {
								$this->request->sendBody();
							}
						} else {
							$this->isFirstHead = false;
						}
					} else {
						@list($name, $value) = preg_split('/:\s*/', $line, 2);
						$this->request->addResponseHeader($name, $value);
					}
				}
			}
			if($i >= $n) {
				$this->readBuf = null;
			}
		}
	}
	
	public function writeHandler($bev, $arg) {
		// $sent = ($this->isHeadSent ? 'body' : 'head');
		// echo "write handler: {$sent}\n";
		if($this->isHeadSent) {
			$this->request->sendBody();
		} else {
			$this->isHeadSent = true;
		}
	}
	
	public function eventHandler($bev, $event, $arg) {
		// echo "event: $event\n";
		if($event & \EventBufferEvent::EOF) {
			$this->free(1, 'Connect error');
		} elseif($event & \EventBufferEvent::ERROR) {
			$this->free(-1, ($event & \EventBufferEvent::READING) ? 'Read error' : 'Write error');
		} elseif($event & \EventBufferEvent::TIMEOUT) {
			$this->free(-2, ($event & \EventBufferEvent::READING) ? 'Read timeout' : 'Write timeout');
		} elseif($event & \EventBufferEvent::CONNECTED) {
			$this->request->connected();
		}
	}
	
	protected $pushIndex;
	public function free(int $errno = 0, string $error = 'OK') {
		if(!$this->event) return;
		
		if($errno === 0 && $this->request && $this->request->isKeepAlive()) {
			static::$events[$this->keepKey][] = $this;
			$this->pushIndex = array_key_last(static::$events[$this->keepKey]);
			$this->event->setTimeouts(1, 1);
		} else {
			\Fwe::pushDns($this->dnsBase);
			$this->event->free();
			$this->event = null;
			$this->ssl_ctx = $this->dnsBase = null;
			if($this->pushIndex !== null) {
				unset(static::$events[$this->keepKey][$this->pushIndex]);
			}
		}
		
		$this->reset();
		$req = $this->request;
		$this->request = null;
		
		if($req) {
			try {
				$req->ok($errno, $error);
			} catch(\Throwable $e) {
				\Fwe::$app->error($e, 'http-client');
			}
		}
	}
}
