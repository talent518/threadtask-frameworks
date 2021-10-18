<?php
namespace fwe\curl;

/**
 * @property-read array $properties
 *
 * @property-read float $beginTime
 * @property-read float $endTime
 * @property-read float $wakeupTime
 * @property-read string $status
 * @property-read string $statusText
 */
class IResponse {
	protected $beginTime;
	protected $endTime;
	protected $wakeupTime;

	protected $status;
	protected $statusText;

	public function __construct() {
		$this->beginTime = microtime(true);
	}

	public function __wakeup() {
		$this->wakeupTime = microtime(true);
	}

	public function __get($name) {
		if($name === 'properties') {
			return get_object_vars($this);
		} else {
			return $this->$name;
		}
	}
	
	public function setStatus(int $status, string $statusText) {
		$this->endTime = microtime(true);
		$this->status = $status;
		$this->statusText = $statusText;
	}
}

