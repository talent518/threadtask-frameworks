<?php
namespace app\ws;

use fwe\base\TsVar;
use fwe\web\IWsEvent;
use fwe\web\WsEvent;

class Monitor implements IWsEvent {
    public static $indexes = 0;
	public static $list = [];
	public static $event;

    /**
     * @var TsVar
     */
	public static $cpu, $mem, $loadavg, $proc, $disk, $net;

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
			static::$event->addTimer(1);
			static::$cpu = new TsVar("monitor-cpu");
			static::$mem = new TsVar("monitor-mem");
			static::$loadavg = new TsVar("monitor-loadavg");
			static::$proc = new TsVar("monitor-proc");
			static::$disk = new TsVar("monitor-disk");
			static::$net = new TsVar("monitor-net");

			$name = \Fwe::$name . ":run:monitor";
            \Fwe::$config->getOrSet($name, function() use($name) {
                return create_task($name, INFILE, []);
            });
		}
		
		static::$list[$this->index] = $this;

		$this->wsEvent->sendMask(json_encode(['action'=>'init', 'data'=>new \stdClass]));
	}

	public function read(string $msg) {
		echo "Monitor: $msg\n";
	}

	public function free() {
		$this->wsEvent = null;

		unset(static::$list[$this->index]);
	}

	protected function send(string $msg) {
		return $this->wsEvent->sendMask($msg);
	}
	
	public static function event() {
		$time = microtime(true);
		$cpu = json_encode(['action'=>'cpu', 'data'=>static::$cpu->all()]);
		$mem = json_encode(['action'=>'mem', 'data'=>static::$mem->all()]);
		$loadavg = json_encode(['action'=>'loadavg', 'data'=>static::$loadavg->all()]);
		$proc = json_encode(['action'=>'proc', 'data'=>static::$proc->all(), 'pid'=>posix_getpid()]);
		$disk = static::$disk->all();
		ksort($disk, SORT_NATURAL);
		$disk = json_encode(['action'=>'disk', 'data'=>$disk]);
		$net = json_encode(['action'=>'net', 'data'=>static::$net->all()]);
		foreach(static::$list as $ws) {
			$ws->send($cpu);
			$ws->send($mem);
			$ws->send($loadavg);
			$ws->send($proc);
			$ws->send($disk);
			$ws->send($net);
		}
	}
}
