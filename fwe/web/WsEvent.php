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

	public function __construct(int $fd, string $addr, int $port, int $key) {
		$this->fd = $fd;
		$this->clientAddr = $addr;
		$this->clientPort = $port;
		$this->key = $key;

		$this->event = new \EventBufferEvent(\Fwe::$base, $this->fd, \EventBufferEvent::OPT_CLOSE_ON_FREE, [$this, 'readHandler'], [$this, 'writeHandler'], [$this, 'eventHandler']);
	}
	
	public function init() {
		$this->event->enable(\Event::READ);
	}
	
	public function eventHandler($bev, $event, $arg) {
		if($event & (\EventBufferEvent::EOF | \EventBufferEvent::ERROR)) {
			$this->event->free();
			\Fwe::$app->setReqEvent($this->key);
		} else {
			echo "key: {$this->key}, event: {$event}\n";
		}
	}
	
	public function writeHandler($bev, $arg) {
		echo __METHOD__, PHP_EOL;
	}
	
	public function readHandler($bev, $arg) {
		echo __METHOD__, PHP_EOL;
	}
	
	public function write($data) {
		return $this->event->write($data);
	}
}
