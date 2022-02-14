<?php
namespace fwe\curl;

/**
 * @property-read array $properties
 *
 * @property-read float $beginTime
 * @property-read float $endTime
 * @property-read float $wakeupTime
 * @property-read integer $errno
 * @property-read string $error
 */
abstract class IResponse {
	protected $beginTime;
	protected $endTime;
	protected $wakeupTime;

	protected $errno;
	protected $error;

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
	
	public function setError(int $errno, string $error) {
		$this->endTime = microtime(true);
		$this->errno = $errno;
		$this->error = $error;
	}

	abstract public function headerHandler($ch, $header);

	abstract public function writeHandler($ch, $data);

	abstract public function progressHandler($ch, int $dlTotal, int $dlBytes, int $upTotal, int $upBytes);
}

