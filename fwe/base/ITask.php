<?php
namespace fwe\base;

/**
 * @property-read integer $run
 * @property-read integer $max
 * @property-read array $list
 */
abstract class ITask {
	protected $run = 0;
	protected $max;
	protected $list = [];
	public function __construct(int $max) {
		$this->max = $max;
	}
	
	public function __get($name) {
		return $this->$name;
	}
	
	public abstract function run($arg);
	
	/**
	 * 
	 */
	public function push($arg) {
		if($this->run < $this->max) {
			$this->run ++;
			$this->run($arg);

			return true;
		} else {
			array_push($this->list, $arg);

			return false;
		}
	}
	
	public function unshift($arg) {
		if($this->run < $this->max) {
			$this->run ++;
			$this->run($arg);

			return true;
		} else {
			array_unshift($this->list, $arg);

			return false;
		}
	}
	
	public function shift() {
		if(empty($this->list)) {
			$this->run --;
			return false;
		} else {
			$this->run(array_shift($this->list));

			return true;
		}
	}
	
	public function pop() {
		if(empty($this->list)) {
			$this->run --;
			return false;
		} else {
			$this->run(array_pop($this->list));
			
			return true;
		}
	}
}
