<?php
namespace fwe\curl;

/**
 * @property-read array $properties
 *
 * @property-read string $status
 * @property-read string $statusText
 */
class IResponse {
	public function __get($name) {
		if($name === 'properties') {
			return get_object_vars($this);
		} else {
			return $this->$name;
		}
	}
	
	public function setStatus(int $status, string $statusText) {
		$this->status = $status;
		$this->statusText = $statusText;
	}
}

