<?php
namespace fwe\web;

use fwe\base\RouteException;
use fwe\utils\StringHelper;
use fwe\base\Application;

class RequestEvent {
	public $clientAddr, $localAddr;
	public $clientPort, $localPort;
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
	const BODY_MODE_XML = 4;
	
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

	/**
	 * @var bool
	 */
	public $isToFile;

	/**
	 * @var float
	 */
	public $time, $recvTime, $runTime;

	/**
	 * @var bool
	 */
	public $keepAlive;
	
	/**
	 * @var mixed
	 */
	public $data;
	
	public function __construct(int $fd, string $addr, int $port, string $addr2, int $port2, int $key, float $keepAlive) {
		$this->fd = $fd;
		$this->clientAddr = $addr;
		$this->clientPort = $port;
		$this->localAddr = $addr2;
		$this->localPort = $port2;
		$this->key = $key;
		$this->time = microtime(true);
		$this->keepAlive = $keepAlive;

		$this->event = new \EventBufferEvent(\Fwe::$base, $this->fd, 0, [$this, 'readHandler'], [$this, 'writeHandler'], [$this, 'eventHandler'], $this->key);
		\Fwe::$app->events++;

		\Fwe::debug(get_called_class(), $this->key, false);
	}
	
	public function init() {
		$this->isToFile = \Fwe::$app->isToFile;
		$this->event->enable(\Event::READ);
	}
	
	public function __destruct() {
		\Fwe::debug(get_called_class(), $this->key, true);

		if($this->fp) {
			fclose($this->fp);
			$this->fp = null;
		}
		
		foreach($this->files as $file) {
			empty($file['path']) or unlink($file['path']);
		}
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

	public function getResponse() {
		if($this->response === null) {
			$this->response = \Fwe::createObject(ResponseEvent::class, [
				'request' => $this,
				'protocol' => $this->protocol??'HTTP/1.0',
			]);
		}

		return $this->response;
	}
	
	private $_isAuth;
	public function isAuth(callable $ok, $title = 'WebDAV') {
		if($this->_isAuth !== null) {
			return $this->_isAuth;
		}
		
		$ret = function(bool $value) use($title) {
			$this->_isAuth = $value;
			
			if(!$value) {
				$response = $this->getResponse();
				$response->headers['WWW-Authenticate'] = 'Basic realm="threadtask-frameworks ' . $title . '"';
				$response->setStatus(401)->end();
			}
		};
		
		if(empty($this->headers['Authorization'])) {
			$ok('', '', $ret);
		} else {
			@list($user, $pass) = explode(':', base64_decode(substr($this->headers['Authorization'], 6)), 2);
			$ok($user, $pass, $ret);
		}
		
		return $this->_isAuth;
	}
	
	public function webSocket($class = null) {
		$isClass = ($class !== null && !is_subclass_of($class['class'] ?? $class, IWsEvent::class));
		if(!isset($this->headers['Upgrade'], $this->headers['Sec-WebSocket-Key'], $this->headers['Sec-WebSocket-Version']) || empty($this->headers['Sec-WebSocket-Key']) || strcasecmp($this->headers['Upgrade'], 'websocket') || $isClass) {
			$response = $this->getResponse()->setStatus(404);
			$response->setContentType('text/plain; charset=utf-8');
			$response->headers['Connection'] = 'close';
			$response->end($isClass ? "class $class is not implements " . IWsEvent::class : "WebSocket Error\n");
			$this->isKeepAlive = false;
		} else {
			$secAccept = base64_encode(pack('H*', sha1($this->headers['Sec-WebSocket-Key'] . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
			$response = $this->getResponse()->setStatus(101);
			$response->headers['Upgrade'] = $this->headers['Upgrade'];
			$response->headers['Connection'] = 'Upgrade';
			$response->headers['WebSocket-Origin'] = $this->getHost();
			$response->headers['WebSocket-Location'] = ($this->isSecure() ? 'wss://' : 'ws://') . $this->getHost() . $this->path;
			$response->headers['Sec-WebSocket-Version'] = $this->headers['Sec-WebSocket-Version'];
			$response->headers['Sec-WebSocket-Accept'] = $secAccept;
			$response->isWebSocket = true;
			$response->wsClass = $class;
			$response->end();
		}
	}
	
	protected $secure;
	public function isSecure() {
		if($this->secure === null) {
			if(isset($this->headers['X-Forwarded-Proto'])) {
				$this->secure = !strcasecmp($this->headers['X-Forwarded-Proto'], 'https');
			} elseif(isset($this->headers['Front-End-Https'])) {
				$this->secure = !strcasecmp($this->headers['Front-End-Https'], 'on');
			}else {
				$this->secure = false;
			}
		}
		
		return $this->secure;
	}
	
	protected $host;
	public function getHost() {
		if($this->host === null) {
			if(isset($this->headers['X-Forwarded-Host'])) {
				$host = $this->headers['X-Forwarded-Host'];
				$pos = strpos($host, ',');
				$this->host = ($pos === false ? $host : substr($host, 0, $pos));
			} elseif(isset($this->headers['X-Original-Host'])) {
				$host = $this->headers['X-Original-Host'];
				$pos = strpos($host, ',');
				$this->host = ($pos === false ? $host : substr($host, 0, $pos));
			} elseif(isset($this->headers['Host'])) {
				$this->host = $this->headers['Host'];
			} else {
				$this->host = "{$this->localAddr}:{$this->localPort}";
			}
		}
		
		return $this->host;
	}
	
	protected $clientIp;
	public function getClientIp() {
		if($this->clientIp === null) {
			if(isset($this->headers['X-Forwarded-For'])) {
				$ip = $this->headers['X-Forwarded-For'];
				$pos = strpos($ip, ',');
				$this->clientIp = ($pos === false ? $ip : substr($ip, 0, $pos));
			} else {
				$this->clientIp = $this->clientAddr;
			}
		}
		
		return $this->clientIp;
	}
	
	protected $onFrees = [];

	public function onFree(callable $free) {
		$this->onFrees[] = $free;
	}
	
	protected $isFree = false;
	public function free(bool $isClose = true) {
		if($this->isFree) return;
		$this->isFree = true;

		\Fwe::$app->events--;

		if($this->head !== null && (\Fwe::$app->logLevel & Application::LOG_ACCESS)) {
			$response = ($this->response ? "{$this->response->protocol} {$this->response->status} {$this->response->statusText}" : 'null');
			$time = round(microtime(true) - $this->recvTime, 6);
			$closed = ($isClose ? 'true' : 'false');
			\Fwe::$app->access("{$this->head} closed({$closed}) key({$this->key}) recv({$this->readlen}) body({$this->bodyoff}/{$this->bodylen}) send({$this->sendlen}) time({$time}) {$response}", 'web');
			unset($response, $time, $closed);
		}
		
		foreach($this->onFrees as $free) {
			$free($this);
		}
		
		if($isClose) {
			\Fwe::$app->decConn();
			$this->event->close();
			// echo "{$this->key} close\n";
		}
		$this->event->free();

		\Fwe::$app->setReqEvent($this->key);

		$this->event = $this->response = $this->action = $this->onFrees = $this->data = null;
		$this->params = [];
	}
	
	/**
	 * @var \fwe\base\Action
	 */
	protected $action;
	
	public function getAction() {
		return $this->action;
	}

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
			if(is_string($buf)) $this->send($buf);
			return;
		}
		
		$status = $this->response->status;
		\Fwe::$app->stat(($status >= 100 && $status < 400) ? 'success' : 'error');
		
		if(!\Fwe::$app->isRunning()) {
			// echo "closed: {$this->key}\n";
			$this->free();
		} elseif($this->response->isWebSocket) {
			$class = $this->response->wsClass;
			\Fwe::$app->decConn();
			$this->free(false);
			$index = $this->get['index'] ?? 0;
			\Fwe::$app->addWs($index, $this->key, [$this->fd, $this->clientAddr, $this->clientPort, $class]);
			// echo "{$this->key} web socket\n";
		} elseif($this->isKeepAlive) {
			$this->free(false);

			$reqEvent = \Fwe::createObject(RequestEvent::class, [
				'fd' => $this->fd,
				'addr' => $this->clientAddr,
				'port' => $this->clientPort,
				'addr2' => $this->localAddr,
				'port2' => $this->localPort,
				'key' => $this->key,
				'keepAlive' => $this->keepAlive,
			]);
			\Fwe::$app->setReqEvent($this->key, $reqEvent);
			// echo "{$this->key} keep alive\n";
		} else {
			$this->free();
		}
	}

	protected function beforeAction() {
		$this->params = ['request'=>$this] + $this->get + $this->cookies;
		$this->action = \Fwe::$app->getAction($this->path, $this->params);
		if(!$this->action->beforeAction($this->params)) {
			throw new RouteException($this->path, "Not Acceptable");
		}
	}

	protected function runAction() {
		$this->runTime = microtime(true);
		$this->params += $this->post;
		
		$ret = $this->action->run($this->params);
		$this->action->afterAction($this->params);

		return $ret;
	}
	
	public function readHandler($bev, $arg) {
		// echo __METHOD__ . ":{$this->key}\n";
		$ret = null;
		try {
			$ret = $this->read();
			if($ret === false || $this->isFree) return;

			if($this->bodylen !== $this->bodyoff || !\Fwe::$app->isRunning()) {
				$this->isKeepAlive = false;
			}

			$this->isKeepAlive = ($this->isKeepAlive && microtime(true) < $this->keepAlive);
			$this->event->disable(\Event::READ);
			
			if($ret) {
				$events = \Fwe::$app->events;
				$response = $this->getResponse();
				$response->headers['Connection'] = ($this->isKeepAlive ? 'keep-alive' : 'close');
				if(!$response->isHeadSent()) {
					$ret = $this->runAction();
					if(is_string($ret)) $response->end($ret);
					elseif(is_array($ret) || is_object($ret)) $response->json($ret);
					elseif(is_int($ret)) $response->setStatus($ret)->json($ret);
					elseif(!$response->isHeadSent() && $events == \Fwe::$app->events) {
						\Fwe::$app->error("Not Content in the route({$this->key}): {$this->action->route}", 'web');
						$response->setStatus(501);
						$response->end();
					}
				}
			} else if($ret === 0) {
				$this->isKeepAlive = false;
				
				$response = $this->getResponse()->setStatus(400);
				$response->setContentType('text/plain');
				$response->headers['Connection'] = 'close';
				$response->end('Bad Request');
			} else {
				$this->isKeepAlive = false;
				
				\Fwe::$app->stat('error');
				$this->free();
			}
		} catch(RouteException $ex) {
			if($this->bodylen !== $this->bodyoff) {
				$this->isKeepAlive = false;
			}

			$this->isKeepAlive = ($this->isKeepAlive && microtime(true) < $this->keepAlive);
			
			if($ex->getMessage() === 'Not Acceptable') {
				$response = $this->getResponse()->setStatus(406);
				$response->setContentType('text/plain; charset=utf-8');
				$response->headers['Connection'] = ($this->isKeepAlive ? 'keep-alive' : 'close');
				$response->end($ex->getMessage());
			} else {
				$response = $this->getResponse()->setStatus(404);
				$response->setContentType('text/plain; charset=utf-8');
				$response->headers['Connection'] = ($this->isKeepAlive ? 'keep-alive' : 'close');
				$response->end($ex->getMessage());
			}
		} catch(\Throwable $ex) {
			\Fwe::$app->error($ex, 'web');
			
			if($this->bodylen !== $this->bodyoff) {
				$this->isKeepAlive = false;
			}

			if($ret) {
				$this->isKeepAlive = ($this->isKeepAlive && microtime(true) < $this->keepAlive);
				
				$response = $this->getResponse()->setStatus(500);
				$response->setContentType('text/plain; charset=utf-8');
				$response->headers['Connection'] = ($this->isKeepAlive ? 'keep-alive' : 'close');
				$response->end($ex->getMessage());
			} else {
				$this->isKeepAlive = false;
				\Fwe::$app->stat('error');
				\Fwe::$app->error($ex, 'web');
				$this->free();
			}
		} finally {
			// printf("%s: %d\n", __METHOD__, __LINE__);
		}
	}
	
	protected $isRecvBody;
	
	public function recv() {
		if($this->isRecvBody === null) return;
		
		$isRecvBody = $this->isRecvBody;
		$this->isRecvBody = null;
		
		if($this->isFree) {
			$key = $this->getKey();
			\Fwe::$app->error("request freed({$key}): {$this->action->route}", 'web');
		} elseif($isRecvBody) {
			$this->event->enable(\Event::READ);
			if(isset($this->headers['Expect']) && $this->headers['Expect'] === '100-continue') {
				if(!$this->send("{$this->protocol} 100 Continue\r\n\r\n")) return null;
			}
			$this->readHandler($this->event, null);
		} else {
			$this->readHandler($this->event, null);
		}
	}
	
	protected $params = [];
	protected function read() {
		if($this->mode === self::MODE_END) return true;
		
		$buf = $this->event->read(16384);
		$n = strlen($buf);
		if($n === 0) return null;

		if($this->recvTime === null) $this->recvTime = microtime(true);
		
		$this->readlen += $n;
		
		// echo ">>>\n$buf";
		
		if($this->buf !== null) {
			$n2 = strlen($this->buf);
			$n += $n2;
			$buf = $this->buf . $buf;
			$this->buf = null;
		} else {
			$n2 = 0;
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
						$this->isHTTP = preg_match('/HTTP\/1\.[01]/', $this->protocol) > 0;
						if(!$this->isHTTP) {
							\Fwe::$app->warn("not http protocol: {$this->head}", 'web');
							return null; // Access Denied
						}
						$uri = parse_url($this->uri);
						if(!$uri || isset($uri['host'])) {
							\Fwe::$app->warn("parse url error or invalid: {$this->uri}", 'web');
							return null;
						}
						if(isset($uri['path'])) {
							$this->path = $uri['path'];
							if(strpos($this->path, '%') !== false) $this->path = urldecode($this->path);
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
						
						$this->isKeepAlive = (isset($this->headers['Connection']) && ($this->headers['Connection'] === 'TE' || !strcasecmp($this->headers['Connection'], 'keep-alive')));
						
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
						
						switch($this->bodytype) {
							case 'application/x-www-form-urlencoded':
								$this->bodymode = self::BODY_MODE_URL_ENCODED;
								break;
							case 'multipart/form-data':
								$this->bodymode = self::BODY_MODE_FORM_DATA;
								$this->boundary = $this->bodyargs['boundary'];
								$this->boundaryBgn = "--{$this->boundary}";
								$this->boundaryPos = "\r\n--{$this->boundary}";
								$this->boundaryEnd = "--{$this->boundary}--";
								break;
							case 'application/json':
								$this->bodymode = self::BODY_MODE_JSON;
								break;
							case 'application/xml':
								$this->bodymode = self::BODY_MODE_XML;
								break;
							default:
								break;
						}
						
						$events = \Fwe::$app->events;
						$this->beforeAction();
						if($events != \Fwe::$app->events) {
							$this->isRecvBody = $this->bodylen > 0;
							if(!$this->isRecvBody) {
								$this->mode = self::MODE_END;
							}
							$this->buf = substr($buf, $i);
							$this->event->disable(\Event::READ);
							return false;
						}
						
						if(isset($this->headers['Expect']) && $this->headers['Expect'] === '100-continue') {
							if(!$this->send("{$this->protocol} 100 Continue\r\n\r\n")) return null;
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
					$this->bodyoff += $n - $i - $n2;
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
											if($this->isToFile) {
												$path = tempnam(ini_get('upload_tmp_dir') ?: sys_get_temp_dir(), 'TTHS_');
												$this->fp = fopen($path, 'wb+');
											} else {
												$this->fp = null;
												$path = null;
											}
											$this->files[$this->formargs['name']] = ['name'=>$this->formargs['filename'], 'type'=>$this->formheaders['Content-Type'], 'path'=>$path, 'size'=>0];
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
												if(isset($this->files[$this->formargs['name']]['size'])) $this->files[$this->formargs['name']]['size'] += $j;
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
										if(isset($this->files[$this->formargs['name']]['size'])) $this->files[$this->formargs['name']]['size'] += $pos - $i;
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
							$buf = substr($buf, $i);
							\Fwe::$app->error("Receive request body error(bodyoff: {$this->bodyoff}, bodylen: {$this->bodylen}): $buf", 'web');
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
										break;
									} else {
										$error = json_last_error_msg();
										\Fwe::$app->warn("JSON error($errno): $error", 'web');
									}
								case self::BODY_MODE_XML:
									try {
										$this->post = StringHelper::xml2array($this->buf);
									} catch(\Throwable $e) {
										\Fwe::$app->warn($e, 'web');
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
		$matches = [];
		$n = preg_match_all('/([^\=]+)\=("([^"]*)"|\'([^\']*)\'|([^;]*));?\s*/', $args, $matches);
		for($i=0; $i<$n; $i++) {
			$ret[$matches[1][$i]] = implode('', [$matches[3][$i], $matches[4][$i], $matches[5][$i]]);
		}
		// var_dump($ret, $head);
		return $ret;
	}
	
	public $sendlen = 0;
	public function send(string $data): bool {
		if($this->isFree) {
			\Fwe::$app->debug("Write freed({$this->key}): $data", 'web');
			return true;
		}

		$ret = $this->event->write($data);
		if($ret) {
			$this->sendlen += strlen($data);
		} else {
			\Fwe::$app->error("Writed error({$this->key}): $data");
		}
		return $ret;
	}
}
