<?php
namespace fwe\web;

use fwe\base\RouteException;

class RequestEvent {
	public $clientAddr = null;
	public $clientPort = 0;
	public $readlen = 0;
	
	public $head = null;
	
	public $method = null;
	public $uri = null;
	public $protocol = null;
	public $isHTTP = false;
	
	public $path = null;
	public $query = null;
	public $fragment = null;
	public $get = [];
	
	public $headers = [];
	public $cookies = [];
	public $post = [];
	public $files = [];
	
	public $isKeepAlive = false;
	
	protected $fd;
	
	public $mode = self::MODE_FIRST;
	public $buf = null;
	public $bodymode = 0;
	public $bodytype = null;
	public $bodyargs = [];
	public $bodylen = 0;
	public $bodyoff = 0;
	
	public $isDav = false;
	
	protected $fp = null;
	protected $formmode = self::FORM_MODE_BOUNDARY;
	protected $boundary = null;
	protected $boundaryBgn = null;
	protected $boundaryPos = null;
	protected $boundaryEnd = null;
	protected $formheaders = [];
	protected $formargs = [];
	
	const MODE_FIRST = 1;
	const MODE_HEAD = 2;
	const MODE_BODY = 3;
	const MODE_END = 4;
	
	const BODY_MODE_URL_ENCODED = 1;
	const BODY_MODE_FORM_DATA = 2;
	const BODY_MODE_JSON = 3;
	
	const FORM_MODE_BOUNDARY = 1;
	const FORM_MODE_HEAD = 2;
	const FORM_MODE_VALUE = 3;
	
	/**
	 * @var \EventBufferEvent
	 */
	protected $event;

	/**
	 * request event index
	 * 
	 * @var integer
	 */
	protected $key;
	
	public function __construct(int $fd, string $addr, int $port, int $key) {
		$this->fd = $fd;
		$this->clientAddr = $addr;
		$this->clientPort = $port;
		$this->key = $key;

		$this->event = new \EventBufferEvent(\Fwe::$base, $this->fd, 0, [$this, 'readHandler'], [$this, 'writeHandler'], [$this, 'eventHandler']);
		// echo __METHOD__ . ":{$this->key}\n";
	}
	
	public function init() {
		$this->event->enable(\Event::READ);
	}
	
	public function __destruct() {
		if($this->fp) {
			fclose($this->fp);
			$this->fp = null;
		}
		
		foreach($this->files as $file) {
			@unlink($file['path']);
		}
		
		// echo __METHOD__ . ":{$this->key}\n";
	}
	
	public function getKey() {
		return $this->key;
	}
	
	public function setFp($fp) {
		if($this->bodylen > 0 && $this->bodyoff === 0) $this->fp = $fp;
	}
	
	/**
	 * @var ResponseEvent
	 */
	protected $response;

	public function getResponse(int $status = 200, $statusText = 'OK'): ResponseEvent {
		if($this->response) return $this->response;

		return $this->response = \Fwe::createObject(ResponseEvent::class, [
			'request' => $this,
			'protocol' => $this->protocol??'HTTP/1.0',
			'status' => $status,
			'statusText' => $statusText
		]);
	}
	
	public function webSocket() {
		if(!isset($this->headers['Upgrade'], $this->headers['Sec-WebSocket-Key'], $this->headers['Sec-WebSocket-Version']) || empty($this->headers['Sec-WebSocket-Key']) || $this->headers['Upgrade'] !== 'websocket') {
			$response = $this->getResponse(404, 'Not Found');
			$response->setContentType('text/plain; charset=utf-8');
			$response->headers['Connection'] = 'close';
			$response->end("WebSocket Error\n");
			$this->isKeepAlive = false;
			
			\Fwe::$app->stat('error');
		} else {
			$host = $this->headers['Host'] ?? '127.0.0.1:5000';
			$secAccept = base64_encode(pack('H*', sha1($this->headers['Sec-WebSocket-Key'] . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
			$response = $this->getResponse(101, 'Web Socket Protocol Handshake');
			$response->headers['Upgrade'] = 'websocket';
			$response->headers['Connection'] = 'Upgrade';
			$response->headers['WebSocket-Origin'] = $host;
			$response->headers['WebSocket-Location'] = 'ws://' . $host . $this->path;
			$response->headers['Sec-WebSocket-Version'] = $this->headers['Sec-WebSocket-Version'];
			$response->headers['Sec-WebSocket-Accept'] = $secAccept;
			$response->isWebSocket = true;
			$response->end();
		}
	}
	
	protected $isFree = false;
	protected function free(bool $isClose = true) {
		if($this->isFree) return;
		$this->isFree = true;

		// echo __METHOD__ . ":{$this->key}\n";

		if($isClose) $this->event->close();
		$this->event->free();
		if($this->action && $this->action->controller) $this->action->controller->actionObjects[$this->action->id] = null;
		$this->event = $this->response = $this->action = null;
		\Fwe::$app->setReqEvent($this->key);
	}
	
	/**
	 * @var \fwe\base\Action
	 */
	protected $action;

	public function eventHandler($bev, $event, $arg) {
		// echo __METHOD__ . ":{$this->key}\n";
		if($event & (\EventBufferEvent::EOF | \EventBufferEvent::ERROR)) {
			$this->free();
		}
	}
	
	public function writeHandler($bev, $arg) {
		// echo __METHOD__ . ":{$this->key}\n";
		if(!$this->response) return;
		if(!$this->response->isEnd) {
			$buf = $this->response->read();
			if($buf === false || $buf === '') $this->response->end();
			else $this->send($buf);
			return;
		}

		if($this->response->isWebSocket) {
			$this->free(false);
			\Fwe::$app->addWs($this->key, [$this->fd, $this->clientAddr, $this->clientPort]);
		} elseif($this->isKeepAlive) {
			$this->free(false);

			$reqEvent = \Fwe::createObject(RequestEvent::class, [
				'fd' => $this->fd,
				'addr' => $this->clientAddr,
				'port' => $this->clientPort,
				'key' => $this->key
			]);
			\Fwe::$app->setReqEvent($this->key, $reqEvent);
		} else {
			$this->free();
		}
	}
	
	public function readHandler($bev, $arg) {
		// echo __METHOD__ . ":{$this->key}\n";
		try {
			$ret = $this->read();
			if($ret === false) return;
			
			if($ret) {
				$this->event->disable(\Event::READ);
				$ret = $this->action->run($this->post + ['actionID' => $this->action->id]);
				if(is_string($ret)) $this->getResponse()->end($ret);
				$this->action->afterAction();
				\Fwe::$app->stat('success');
				return;
			} else if($ret === 0) {
				$response = $this->getResponse(400, 'Bad Request');
				$response->setContentType('text/plain');
				$response->headers['Connection'] = 'close';
				$response->end('Bad Request');
	
				\Fwe::$app->stat('error');
			} else {
				\Fwe::$app->stat('error');
				$this->free();
			}
		} catch(RouteException $ex) {
			$response = $this->getResponse(404, 'Not Found');
			$response->setContentType('text/plain; charset=utf-8');
			$response->headers['Connection'] = 'close';
			$response->end($ex->getMessage());
			
			\Fwe::$app->stat('error');
		} catch(\Throwable $ex) {
			echo "Throwable: $ex\n";

			\Fwe::$app->stat('error');
			$this->free();
		}
		$this->isKeepAlive = false;
	}
	
	protected function read() {
		if($this->mode === self::MODE_END) return true;
		
		$buf = $this->event->read(16384);
		$n = strlen($buf);
		
		$this->readlen += $n;
		
		// echo ">>>\n$buf";
		
		if($this->buf !== null) {
			$n += strlen($this->buf);
			$buf = $this->buf . $buf;
			$this->buf = null;
		}
		
		$i = 0;
		while($i < $n) {
			switch($this->mode) {
				case self::MODE_FIRST:
					$pos = strpos($buf, "\r\n", $i);
					if($pos === false) {
						$this->buf = substr($buf, $i);
						$i = $n;
					} else {
						$this->head = substr($buf, $i, $pos-$i);
						@list($this->method, $this->uri, $this->protocol) = explode(' ', $this->head, 3);
						$uri = parse_url($this->uri);
						if(isset($uri['path'])) {
							$this->path = $uri['path'];
							if(strpos($this->path, '%') !== false) $this->path = urldecode($this->path);
							$this->isHTTP = preg_match('/HTTP\/1\.[01]/', $this->protocol) > 0;
						}
						if(isset($uri['query'])) {
							$this->query = $uri['query'];
							parse_str($uri['query'], $this->get);
						}
						if(isset($uri['fragment'])) {
							$this->fragment = $uri['fragment'];
						}
						$i = $pos + 2;
						$this->mode = self::MODE_HEAD;
					}
					break;
				case self::MODE_HEAD:
					$pos = strpos($buf, "\r\n", $i);
					if($pos === false) {
						$this->buf = substr($buf, $i);
						$i = $n;
					} elseif($i === $pos) {
						$i += 2;
						
						$this->bodylen = (int) ($this->headers['Content-Length'] ?? 0);
						if($this->bodylen < 0) $this->bodylen = 0;
						$this->mode = ($this->bodylen ? self::MODE_BODY : self::MODE_END);
						
						$this->isKeepAlive = (isset($this->headers['Connection']) && !strcasecmp($this->headers['Connection'], 'keep-alive'));
						
						$args = $this->getHeadArgs($this->headers['Content-Type'] ?? '');
						$this->bodytype = $args[0];
						unset($args[0]);
						$this->bodyargs = $args;
						
						if(!empty($this->headers['Cookie'])) {
							$cookies = (array) $this->headers['Cookie'];
							foreach($cookies as $cookie) {
								if(($cookie = preg_split('/;\s+/', $cookie, -1, PREG_SPLIT_NO_EMPTY)) !== false) {
									foreach($cookie as $_cookie) {
										@list($name, $value) = explode('=', $_cookie, 2);
										$this->cookies[$name] = urldecode($value);
									}
								}
							}
						}
						
						$params = ['request'=>$this] + $this->get;
						$this->action = \Fwe::$app->getAction($this->path, $params);
						if(!$this->action->beforeAction()) {
							throw new RouteException($this->path, "没有发现路由\"{$this->path}\"");
						}
						
						if(isset($this->headers['Expect']) && $this->headers['Expect'] === '100-continue') {
							if(!$this->send("{$this->protocol} 100 Continue\r\n\r\n")) return null;
						}
						
						switch($this->bodytype) {
							case 'application/x-www-form-urlencoded':
								$this->bodymode = self::BODY_MODE_URL_ENCODED;
								break;
							case 'multipart/form-data':
								$this->bodymode = self::BODY_MODE_FORM_DATA;
								$this->boundary = $this->bodyargs['boundary'];
								$this->boundaryBgn = '--' . $this->boundary;
								$this->boundaryPos = "\r\n--{$this->boundary}";
								$this->boundaryEnd = '--' . $this->boundary . '--';
								break;
							case 'application/json':
								$this->bodymode = self::BODY_MODE_JSON;
								break;
							default:
								break;
						}
					} else {
						@list($name, $value) = preg_split('/:\s*/', substr($buf, $i, $pos-$i), 2);
						if(isset($this->headers[$name])) {
							$_value = & $this->headers[$name];
							if(!is_array($_value)) {
								$_value = (array) $_value;
							}
							$_value[] = $value;
						} else {
							$this->headers[$name] = $value;
						}
						$i = $pos + 2;
					}
					break;
				case self::MODE_BODY:
					// echo 'BODYOFF: ', $n-$i, "\n";
					$this->bodyoff += $n - $i - strlen($this->buf);
					if($this->bodymode === self::BODY_MODE_FORM_DATA) {
						while($i < $n) {
							switch($this->formmode) {
								case self::FORM_MODE_BOUNDARY:
									$pos = strpos($buf, "\r\n", $i);
									if($pos === false) {
										$this->buf = substr($buf, $i);
										$i = $n;
									} elseif(($boundary = substr($buf, $i, $pos-$i)) === $this->boundaryBgn) {
										$this->formmode = self::FORM_MODE_HEAD;
										$i = $pos + 2;
									} elseif($boundary === $this->boundaryEnd) {
										$this->mode = self::MODE_END;
										$this->buf = null;
										$i = $n;
									} else {
										$this->buf = substr($buf, $i);
										// var_dump('BOUNDARY', $boundary, $this->boundaryBgn, $this->boundaryEnd);
										return 0;
									}
									break;
								case self::FORM_MODE_HEAD:
									$pos = strpos($buf, "\r\n", $i);
									if($pos === false) {
										$this->buf = substr($buf, $i);
										$i = $n;
									} elseif($i === $pos) {
										$this->formmode = self::FORM_MODE_VALUE;
										$i += 2;
										
										$this->formargs = $this->getHeadArgs($this->formheaders['Content-Disposition']);
										if($this->formargs[0] !== 'form-data') {
											$this->buf = null;
											return 0;
										}
										if(isset($this->formargs['filename'], $this->formheaders['Content-Type'])) {
											if(\Fwe::$app->isToFile) {
												$path = tempnam(ini_get('upload_tmp_dir') ?: sys_get_temp_dir(), 'TTHS_');
												$this->fp = fopen($path, 'wb+');
											} else {
												$this->fp = null;
												$path = null;
											}
											$this->files[$this->formargs['name']] = ['name'=>$this->formargs['filename'], 'type'=>$this->formheaders['Content-Type'], 'path'=>$path];
										}
									} else {
										@list($name, $value) = preg_split('/:\s*/', substr($buf, $i, $pos-$i), 2);
										$this->formheaders[$name] = $value;
										$i = $pos + 2;
									}
									break;
								case self::FORM_MODE_VALUE:
									$pos = strpos($buf, $this->boundaryPos, $i);
									if($pos === false) {
										if($this->fp) {
											$j = $n - $i - strlen($this->boundaryPos) + 1;
											if($j > 0) {
												fwrite($this->fp, substr($buf, $i, $j), $j);
												$this->buf = substr($buf, $i + $j);
											} else {
												$this->buf = substr($buf, $i);
											}
										} else {
											$this->buf = substr($buf, $i);
										}
										$i = $n;
									} else {
										$value = substr($buf, $i, $pos - $i);
										if($this->fp) {
											fwrite($this->fp, $value, strlen($value));
											fclose($this->fp);
											$this->fp = null;
										} else {
											$this->post[$this->formargs['name']] = $value;
										}
										$i = $pos + 2;
										$this->formmode = self::FORM_MODE_BOUNDARY;
										$this->formheaders = $this->formargs = [];
									}
									break;
							}
						}
						
						if($this->bodyoff >= $this->bodylen && $this->mode !== self::MODE_END) {
							echo "BODYOFF: $buf\n";
							return 0;
						}
					} elseif($this->fp) {
						if($i === 0) {
							fwrite($this->fp, $buf, $n);
						} else {
							fwrite($this->fp, substr($buf, $i), $n - $i);
						}
						if($this->bodyoff >= $this->bodylen) {
							fclose($this->fp);
							$this->fp = null;
							$this->mode = self::MODE_END;
						}
					} else {
						$this->buf = substr($buf, $i);
						if($this->bodyoff >= $this->bodylen) {
							$this->mode = self::MODE_END;
							switch($this->bodymode) {
								case self::BODY_MODE_URL_ENCODED:
									parse_str($this->buf, $this->post);
									break;
								case self::BODY_MODE_JSON:
									$json = json_decode($this->buf, true);
									$errno = json_last_error();
									if($errno === JSON_ERROR_NONE) {
										if(is_array($json)) {
											$this->post = & $json;
										}
									} else {
										fprintf(STDERR, "JSON(%d): %s\n", $errno, json_last_error_msg());
									}
									break;
								default:
									
									break;
							}
						}
					}
					$i = $n;
					break;
				default: // MODE_END
					$this->buf = null;
					$i = $n;
					break;
			}
		}
		
		return $this->mode === self::MODE_END;
	}
	
	public function getHeadArgs($head): array {
		@list($arg0, $args) = preg_split('/;\s*/', $head, 2);
		$ret = [$arg0];
		$n = preg_match_all('/([^\=]+)\=("([^"]*)"|\'([^\']*)\'|([^;]*));?\s*/', $args, $matches);
		for($i=0; $i<$n; $i++) {
			$ret[$matches[1][$i]] = implode('', [$matches[3][$i], $matches[4][$i], $matches[5][$i]]);
		}
		// var_dump($ret, $head);
		return $ret;
	}
	
	public function send(string $data): bool {
		// echo "<<<\n$data";
		
		if($this->isFree) return true;
		return $this->event->write($data);
	}
}
