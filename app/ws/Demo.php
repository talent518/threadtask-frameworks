<?php
namespace app\ws;

use fwe\web\IWsEvent;
use fwe\web\WsEvent;

class Demo implements IWsEvent {
	public static $indexes = 0;
	public static $list = [];
	public static $event;

	/**
	 * @var WsEvent
	 */
	private $wsEvent;
	
	/**
	 * @var int
	 */
	private $index;

	public function __construct(WsEvent $wsEvent) {
		$this->wsEvent = $wsEvent;
	}

	public function init() {
		$this->index = static::$indexes ++;
		
		if($this->index === 0) {
			static::$event = new \Event(\Fwe::$base, -1, \Event::TIMEOUT | \Event::PERSIST, __CLASS__ . '::event');
			static::$event->addTimer(30);
		}
		
		static::$list[$this->index] = $this;

		$this->wsEvent->sendMask('init');
	}

	public function read(string $msg) {
		$this->wsEvent->sendMask("read: $msg");
	}
	
	public function timeout(float $time) {
		$this->wsEvent->sendMask("timestamp: $time");
	}

	public function free() {
		$this->wsEvent = null;

		unset(static::$list[$this->index]);
	}
	
	public static function event() {
		$time = microtime(true);
		foreach(static::$list as $ws) {
			$ws->timeout($time);
		}
	}
}
