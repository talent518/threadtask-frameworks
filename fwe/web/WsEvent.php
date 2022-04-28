<?php
namespace fwe\web;

class WsEvent {
	public $clientAddr = null;
	public $clientPort = 0;

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
	 * @var string
	 */
	public $doClass;
	
	/**
	 * do action class
	 * 
	 * @var IWsEvent
	 */
	protected $doObj;

	public function __construct(int $fd, string $addr, int $port, int $key, $doClass) {
		$this->fd = $fd;
		$this->clientAddr = $addr;
		$this->clientPort = $port;
		$this->key = $key;
		$this->doClass = $doClass;

		$this->event = new \EventBufferEvent(\Fwe::$base, $this->fd, \EventBufferEvent::OPT_CLOSE_ON_FREE, [$this, 'readHandler'], [$this, 'writeHandler'], [$this, 'eventHandler'], $this->key);
		\Fwe::$app->events++;
		
		if($doClass) {
			$this->doObj = \Fwe::createObject($doClass, ['wsEvent' => $this]);
		} else {
			$msg = $this->mask("Connected {$addr}:{$port}");
			$this->event->write($msg);
			\Fwe::$app->sendWs($msg);
		}

		\Fwe::debug(get_called_class(), $this->key, false);
	}
	
	public function init() {
		$this->event->enable(\Event::READ);
	}
	
	public function __destruct() {
		\Fwe::debug(get_called_class(), $this->key, true);
	}
	
	protected $isFree = false;
	protected function free() {
		if($this->isFree) return;
		$this->isFree = true;

		\Fwe::$app->events--;

		// echo __METHOD__ . ":{$this->key}\n";

		if($this->doObj) {
			$this->doObj->free();
			$this->doObj = null;
		}
		
		$this->event->free();
		$this->event = null;
		\Fwe::$app->setReqEvent($this->key);
	}
	
	public function eventHandler($bev, $event, $arg) {
		if($event & (\EventBufferEvent::EOF | \EventBufferEvent::ERROR)) {
			$this->free();
		} else {
			\Fwe::$app->debug("key: {$this->key}, event: {$event}", 'web-socket');
		}
	}
	
	public function writeHandler($bev, $arg) {
		// echo __METHOD__, PHP_EOL;
	}
	
	protected $buffer;
	public function readHandler($bev, $arg) {
		$buf = $this->event->read(16384);
		if($buf === false) {
		close:
			$this->free();
			return;
		}
		
		// unmask for message
		if($this->buffer !== null) {
			if(is_string($this->buffer)) {
				$buf = $this->buffer . $buf;
				$this->buffer = null;
			} else {
				$masks = $this->buffer[0];
				$buf = $this->buffer[1] . $buf;
				$length = $this->buffer[2];
				$ctl = $this->buffer[3];
				
				$n = strlen($buf);
				if($n < $length) {
					$this->buffer[1] = $buf;
					$buf = null;
					return;
				} elseif($n == $length) {
					$this->buffer = null;
				} else {
					$this->buffer = substr($buf, $length);
					$buf = substr($buf, 0, $length);
				}
				goto unmask;
			}
		}
	unpack:
		$n = strlen($buf);
		if($n < 6) {
			trybuf:
			$this->buffer = $buf;
			$buf = null;
			return;
		}
		$cc = unpack('C2', $buf);
		if(!($cc[1] & 0x80) || ($cc[1] & 0x70) || !($cc[2] & 0x80)) { // !FIN || (RSV1 || RSV2 || RSV3) || !Mask
			\Fwe::$app->stat('error');
			goto close;
		}
		$ctl = $cc[1] & 0xf;
		$length = $cc[2] & 0x7f;
		unset($cc);
		if($length == 126) {
			if($n < 8) goto trybuf;
			$length = unpack('n', $buf, 2)[1];
			$masks = substr($buf, 4, 4);
			$buf = substr($buf, 8);
		} elseif($length == 127) {
			if($n < 14) goto trybuf;
			$length = unpack('J', $buf, 2)[1];
			$masks = substr($buf, 10, 4);
			$buf = substr($buf, 14);
		} else {
			$masks = substr($buf, 2, 4);
			$buf = substr($buf, 6);
		}
		$n = strlen($buf);
		if($n < $length) {
			$this->buffer = [$masks, $buf, $length, $ctl];
			$buf = $masks = null;
			return;
		} elseif($n > $length) {
			$this->buffer = substr($buf, $length);
			$buf = substr($buf, 0, $length);
		}
	unmask:
		$text = "";
		for($_i = 0; $_i < $length; ++$_i) {
			$text .= $buf[$_i] ^ $masks[$_i % 4];
		}
		$buf = $text;

		// echo "ctl: $ctl\n";
		switch($ctl) {
			case 0x1: // text
			case 0x2: // binary
				break;
			case 0x8: // close
				if($length > 2) {
					$errno = unpack('n', $buf)[1];
					$error = substr($buf, 2);
				} else {
					$errno = 0;
					$error = 'OK';
				}
				\Fwe::$app->setReqEvent($this->key);
				if(!$this->doObj) {
					\Fwe::$app->sendWs($this->mask("Disconnected {$this->clientAddr}:{$this->clientPort} Error($errno): $error"));
				}
				goto close;
				break;
			case 0x9: // ping
				//echo "ping: $buf\n";
				if($this->event->write($this->mask($buf, 0x8a)) === false) goto close;
				goto next;
				break;
			case 0xA: // pong
				//echo "pong: $buf\n";
				goto next;
				break;
			default:
				\Fwe::$app->stat('error');
				goto close;
				break;
		}
		
		\Fwe::$app->stat('success');

		// send message
		if($this->doObj) {
			try {
				$this->doObj->read($buf);
			} catch(\Throwable $ex) {
				\Fwe::$app->error($ex, 'web-socket');
			}
		} else {
			\Fwe::$app->sendWs($this->mask($buf));
		}

	next:
		if($this->buffer !== null) {
			$buf = $this->buffer;
			$this->buffer = null;
			goto unpack;
		}
	}
	
	public function mask(string $txt, int $ctl = 0x81) {
		$n = strlen($txt);
		if($n <= 125)
			return pack('CC', $ctl, $n) . $txt;
		elseif($n > 125 && $n < 65536)
			return pack('CCn', $ctl, 126, $n) . $txt;
		else
			return pack('CCJ', $ctl, 127, $n) . $txt;
	}
	
	public function send($data) {
		return $this->event->write($data);
	}
	
	public function sendMask(string $txt, int $ctl = 0x81) {
		return $this->send($this->mask($txt, $ctl));
	}
}
