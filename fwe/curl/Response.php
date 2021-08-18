<?php
namespace fwe\curl;

/**
 * @property-read array $properties
 *
 * @property-read boolean $isHTTP
 * @property-read string $protocol
 * @property-read string $status
 * @property-read string $statusText
 * @property-read array $headers
 * @property-read string $data
 */
class Response {
	protected $isHTTP;

	protected $protocol;
	protected $status;
	protected $statusText;

	protected $headers = [];

	protected $data;
	
	public function __construct(string $protocol) {
		$this->isHTTP = preg_match('/^https?$/i', $protocol) > 0;
	}
	
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
	
	/**
	 * @param resource $ch
	 * @param string $header
	 */
	public function headerHandler($ch, $header) {
		if($this->protocol) {
			if(strchr($header, ':') === false) return strlen($header);
			
			list($name, $value) = preg_split('/\:\s*/', $header, 2);

			$value = trim($value);
	
			if(isset($this->headers[$name])) {
				if(is_array($this->headers[$name])) {
					$this->headers[$name][] = $value;
				} else {
					$this->headers[$name] = [$this->headers[$name], $value];
				}
			} else {
				$this->headers[$name] = $value;
			}
		} else {
			list($this->protocol, $status, $statusText) = preg_split('/\s+/', $header, 3);
			$this->status = (int) $status;
			$this->statusText = trim($statusText);
		}

		return strlen($header);
	}
	
	public function writeHandler($ch, $data) {
		$this->data .= $data;

		return strlen($data);
	}
}
